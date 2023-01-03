<?php

namespace App\Jobs;

use App\Models\DataSet;
use Carbon\Carbon;
use GrumpyDictator\FFIIIApiSupport\Exceptions\ApiHttpException;
use GrumpyDictator\FFIIIApiSupport\Model\Transaction;
use GrumpyDictator\FFIIIApiSupport\Model\TransactionGroup;
use GrumpyDictator\FFIIIApiSupport\Request\GetTransactionsRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Class ProcessTransactions
 */
class ProcessTransactions implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public string   $identifier;
    public string   $url;
    public string   $token;
    public array    $ignoreAccounts;
    public array    $ignoreBudgets;
    public array    $ignoreCategories;
    public bool     $drawDestination;
    public int      $tries        = 5;
    private DataSet $dataSet;
    private string  $budgeted     = 'All money';
    private bool    $error        = false;
    private string  $errorMessage = '';
    private Carbon  $start;
    private Carbon  $end;
    private string  $sourceGrouping;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(string $identifier, array $parameters)
    {
        $this->identifier       = $identifier;
        $this->url              = $parameters['url'];
        $this->token            = $parameters['token'];
        $this->ignoreAccounts   = $parameters['ignore_accounts'];
        $this->ignoreBudgets    = $parameters['ignore_budgets'];
        $this->ignoreCategories = $parameters['ignore_categories'];
        $this->drawDestination  = $parameters['draw_destinations'];
        $this->start            = $parameters['start'];
        $this->end              = $parameters['end'];
        $this->sourceGrouping   = $parameters['source_grouping'];
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        Log::debug(sprintf('Now in job %s', $this->identifier));
        $dataset = DataSet::where('identifier', $this->identifier)->first();
        if (null === $dataset) {
            $dataset             = new DataSet();
            $dataset->identifier = $this->identifier;
            $dataset->data       = json_encode(
                [
                    'processing'    => true,
                    'error'         => false,
                    'error_message' => '',
                ]
            );
            $dataset->save();
        }
        $this->dataSet = $dataset;

        // download transactions using the API.
        // in = the revenue account the money comes from
        // middle: the budget or (not budgeted)
        // out: the expense account the money goes to.
        $this->startJob();
        Log::debug(sprintf('Done with job %s', $this->identifier));
    }

    /**
     * @return void
     */
    private function startJob(): void
    {
        if (str_ends_with($this->url, '/')) {
            $this->url = substr($this->url, 0, -1);
        }

        $transactions = $this->downloadTransactions();
        Log::debug(sprintf('Total transactions: %d', count($transactions)));
        /*
         * Create a flow where the money goes from category X,
         * to budget X, and then to category X (if any).
         */
        $basicDiagram        = $this->createBasicDiagram($transactions);
        $this->dataSet->data =
            json_encode([
                'processing'    => false,
                'error'         => $this->error,
                'error_message' => $this->errorMessage,
                'basic'         => $basicDiagram,
            ]);
        $this->dataSet->save();
    }

    /**
     * @return array
     */
    private function downloadTransactions(): array
    {
        $return = [];
        Log::debug(sprintf('Downloading transactions for %s', $this->identifier));
        Log::debug(sprintf('Firefly III URL: %s', $this->url));
        // download per month, join results, then return.
        $date = clone $this->start;
        $end  = clone $this->end;
        while ($date->isBefore($end)) {
            $endOfMonth = clone $date;
            $endOfMonth->endOfMonth();

            // small catch in case we overflow:
            if ($endOfMonth->isAfter($end)) {
                $endOfMonth = clone $end;
            }

            Log::debug(sprintf('Period is now %s to %s', $date->format('Y-m-d'), $endOfMonth->format('Y-m-d')));

            // first download withdrawals:
            $withdrawals = new GetTransactionsRequest($this->url, $this->token);
            $withdrawals->setFilter($date->format('Y-m-d'), $endOfMonth->format('Y-m-d'), 'withdrawal');
            try {
                $result = $withdrawals->get();
            } catch (ApiHttpException $e) {
                Log::error(sprintf('Could not download from %s', $this->url));
                Log::error($e->getMessage());
                $this->error        = true;
                $this->errorMessage = $e->getMessage();
                return [];
            }
            $count = 0;
            /** @var TransactionGroup $transaction */
            foreach ($result as $transaction) {
                $return[] = $transaction;
                $count++;
            }
            Log::debug(sprintf('Downloaded %d withdrawal(s)', $count));

            // then download deposits:
            $deposits = new GetTransactionsRequest($this->url, $this->token);
            $deposits->setFilter($date->format('Y-m-d'), $endOfMonth->format('Y-m-d'), 'deposit');
            $result = $deposits->get();
            $count  = 0;
            /** @var Transaction $transaction */
            foreach ($result as $transaction) {
                $return[] = $transaction;
                $count++;
            }
            Log::debug(sprintf('Downloaded %d deposit(s)', $count));

            $date->addMonth();
        }

        Log::debug(sprintf('Done downloading transactions for %s', $this->identifier));
        return $return;
    }

    /**
     * Each transaction makes money flow from A to B.
     * The key is used to distinguish different combinations.
     * The "sort" key is used to prioritize values, this makes the resulting JS easier to view.
     *
     * @param  array  $transactions
     * @return array
     */
    private function createBasicDiagram(array $transactions): array
    {
        Log::debug(sprintf('Generate basic diagram from %d transaction(s)', count($transactions)));
        $result  = [];
        $ignored = 0;
        /** @var TransactionGroup $group */
        foreach ($transactions as $group) {
            /** @var Transaction $transaction */
            foreach ($group->transactions as $transaction) {
                $amount = (float)$transaction->amount;
                // ignore transaction if the account is set to be ignored
                if ($this->ignoreByAccount($transaction)) {
                    $ignored++;
                    continue;
                }

                if ('withdrawal' === $transaction->type) {
                    if (in_array($transaction->categoryId, $this->ignoreCategories, true)) {
                        $ignored++;
                        continue;
                    }
                    if (in_array($transaction->budgetId, $this->ignoreBudgets, true)) {
                        $ignored++;
                        continue;
                    }

                    /**
                     *
                     * expenses flow from a budget (or no budget at all)
                     * to a category.
                     */
                    $sort                   = '12';
                    $budget                 = '' === (string)$transaction->budgetName ? '(no budget)' : sprintf('Budget: %s', $transaction->budgetName);
                    $category               = '' === (string)$transaction->categoryName ? '(no category)' : sprintf('Category: %s', $transaction->categoryName);
                    $key                    = sprintf('%d-%s-%s', $sort, $budget, $category);
                    $result[$key]           = $result[$key] ??
                                              [
                                                  'from'   => $budget,
                                                  'to'     => $category,
                                                  'amount' => 0.0,
                                              ];
                    $result[$key]['amount'] += $amount;
                    /**
                     * All money further flows from a category to a specific revenue account (optional)
                     */
                    if ($this->drawDestination) {
                        $sort                   = '13';
                        $destination            = $transaction->destinationName;
                        $key                    = sprintf('%s-%s-%s', $sort, $category, $destination);
                        $result[$key]           = $result[$key] ??
                                                  [
                                                      'from'   => $category,
                                                      'to'     => $destination,
                                                      'amount' => 0.0,
                                                  ];
                        $result[$key]['amount'] += $amount;
                    }

                    /**
                     * All spent money comes from "all your money", as a placeholder for all your money.
                     */
                    $sort                   = '11';
                    $key                    = sprintf('%d-%s-%s', $sort, $this->budgeted, $budget);
                    $result[$key]           = $result[$key] ??
                                              [
                                                  'from'   => $this->budgeted,
                                                  'to'     => $budget,
                                                  'amount' => 0.0,
                                              ];
                    $result[$key]['amount'] += $amount;
                    unset($budget, $category);
                }
                // if is a deposit, then from = category, and to = "Budgeted"
                if ('deposit' === $transaction->type) {
                    if (in_array($transaction->categoryId, $this->ignoreCategories, true)) {
                        $ignored++;
                        continue;
                    }
                    // source name defaults to category.
                    $sourceName = '' === (string)$transaction->categoryName ? '(no category)' : sprintf('In: %s', $transaction->categoryName);
                    // but it could be revenue account name:
                    if('revenue' === $this->sourceGrouping) {
                        $sourceName = '' === (string)$transaction->sourceName ? '(cash)' : sprintf('In: %s', $transaction->sourceName);
                    }

                    $sort                   = '10';
                    $key                    = sprintf('%d-%s-%s', $sort, $sourceName, $this->budgeted);
                    $result[$key]           = $result[$key] ??
                                              [
                                                  'from'   => $sourceName,
                                                  'to'     => $this->budgeted,
                                                  'amount' => 0.0,
                                              ];
                    $result[$key]['amount'] += $amount;
                }
            }
        }
        // sort by key.
        uasort(
            $result,
            function (array $a, array $b) {
                return $b['amount'] <=> $a['amount'];
            }
        );
        ksort($result);
        Log::debug(sprintf('Generated basic diagram with %d flows from %d (%d ignored) transaction(s)', count($result), count($transactions), $ignored));

        return $result;
    }

    /**
     * @param  Transaction  $transaction
     * @return bool
     */
    private function ignoreByAccount(Transaction $transaction): bool
    {
        return in_array($transaction->sourceId, $this->ignoreAccounts, true) || in_array($transaction->destinationId, $this->ignoreAccounts, true);
    }
}
