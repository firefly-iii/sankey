<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessTransactions;
use App\Models\DataSet;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Ramsey\Uuid\Uuid;
use Log;

/**
 * Class IndexController
 */
class IndexController
{
    /**
     * @return \Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\View\Factory|\Illuminate\Contracts\View\View
     */
    public function index()
    {
        if (!session()->has('identifier')) {
            $uuid = Uuid::uuid4();
            session()->put('identifier', $uuid->toString());
        }
        return view('index');
    }

    /**
     * @param  Request  $request
     * @return void
     */
    public function post(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'url'               => 'required|url',
            'token'             => 'required',
            'year'              => 'required|numeric|min:2000|max:2025',
            'ignore_accounts'   => 'min:0,max:255',
            'ignore_categories' => 'min:0,max:255',
            'ignore_budgets'    => 'min:0,max:255',
        ]);
        if ($validator->fails()) {
            return redirect(route('index'))->withErrors($validator)->withInput();
        }
        $validated                      = $validator->validated();
        $validated['draw_destinations'] = $request->has('draw_destinations');
        $validated['ignore_accounts']   = explode(',', $validated['ignore_accounts']);
        $validated['ignore_categories'] = explode(',', $validated['ignore_categories']);
        $validated['ignore_budgets']    = explode(',', $validated['ignore_budgets']);

        $validated['year']              = (int)$validated['year'];
        $validated['ignore_accounts']   = array_map('intval', $validated['ignore_accounts']);
        $validated['ignore_categories'] = array_map('intval', $validated['ignore_categories']);
        $validated['ignore_budgets']    = array_map('intval', $validated['ignore_budgets']);

        $identifier = session()->get('identifier');
        Log::debug(sprintf('Stored new job under identifier %s', $identifier));
        ProcessTransactions::dispatch($identifier, $validated);
        return redirect(route('diagram'));
    }

    /**
     * @param  Request  $request
     * @return \Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\View\Factory|\Illuminate\Contracts\View\View
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    public function diagram(Request $request)
    {
        $identifier = session()->get('identifier');
        $dataSet    = DataSet::where('identifier', $identifier)->first();
        if (null === $dataSet) {
            return view('diagram-nodata');
        }
        $data = json_decode($dataSet->data, true);
        if (true === $data['processing']) {
            return view('diagram-nodata');
        }
        if (true === $data['error']) {
            return view('diagram-error')->with('error', $data['error_message']);
        }
        //var_dump(json_decode($data, true));

        return view('diagram')->with('data', $data);
    }

    /**
     * @param  Request  $request
     * @return \Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\View\Factory|\Illuminate\Contracts\View\View
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    public function destroy(Request $request)
    {
        $identifier = session()->get('identifier');
        $dataSet    = DataSet::where('identifier', $identifier)->delete();
        session()->forget('identifier');
        return redirect(route('index'));
    }

}
