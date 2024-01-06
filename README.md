# Firefly III Sankey Diagram generator

## Introduction

A sankey diagram is a diagram that shows you the flow of things. In this case, money! Here's an example. The categories and budget labels are Dutch, sorry about that. Feel free to submit a better example!

![Example Sankey Diagram](sankey-example.png "Example Sankey Diagram")

## Generate your own diagram

This tool uses the [Firefly III](https://www.firefly-iii.org/) [API](https://api-docs.firefly-iii.org/) to generate such a diagram for you. You can tune it by excluding accounts, budgets and categories. Optionally, you can generate a diagram that includes all destination accounts (but this gets messy very quickly).

You can download and install the tool yourself or use the **[online version](https://sankey.firefly-iii.org/)**.

There is no Docker image and no installation instructions, I just built this tool for the fun of it.

<!-- SPONSOR TEXT -->

## Support the development of Firefly III

If you like Firefly III and if it helps you save lots of money, why not send me a dime for every dollar saved! 🥳

OK that was a joke. If you feel Firefly III made your life better, please consider contributing as a sponsor. Please check out my [Patreon](https://www.patreon.com/jc5) and [GitHub Sponsors](https://github.com/sponsors/JC5) page for more information. You can also [buy me a ☕️ coffee at ko-fi.com](https://ko-fi.com/Q5Q5R4SH1). Thank you for your consideration.

<!-- END OF SPONSOR TEXT -->

## License

This work [is licensed](https://github.com/firefly-iii/firefly-iii/blob/main/LICENSE) under the [GNU Affero General Public License v3](https://www.gnu.org/licenses/agpl-3.0.html).

<!-- HELP TEXT -->

## Do you need help, or do you want to get in touch?

Do you want to contact me? You can email me at [james@firefly-iii.org](mailto:james@firefly-iii.org) or get in touch through one of the following support channels:

- [GitHub Discussions](https://github.com/firefly-iii/firefly-iii/discussions/) for questions and support
- [Gitter.im](https://gitter.im/firefly-iii/firefly-iii) for a good chat and a quick answer
- [GitHub Issues](https://github.com/firefly-iii/firefly-iii/issues) for bugs and issues
- <a rel="me" href="https://fosstodon.org/@ff3">Mastodon</a> for news and updates

<!-- END OF HELP TEXT -->

## Disclaimer

This tool works by downloading and parsing all your deposits and withdrawals. This happens entirely offline and no individual transactions are saved or cached. Running it yourself is very safe. 

But if you use the [online version](https://sankey.firefly-iii.org/) you have to be very sure you trust me with your data. I could be totally lying, the server could be hacked, etc. etc. I'm not responsible for anything that happens to your data. You have been warned.
