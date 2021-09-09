# PHP Wrapper for callas pdfToolbox

A PHP wrapper class for [callas pdfToolbox](https://www.callassoftware.com/en/products/pdftoolbox).

## Installation

This library is installed via [Composer](http://getcomposer.org/). To install, use `composer require pointybeard/pdftoolbox` or add `"pointybeard/pdftoolbox": "~1.0.0"` to your `composer.json` file.

And run composer to update your dependencies:

    $ curl -s http://getcomposer.org/installer | php
    $ php composer.phar update

### Requirements

This library requires pdfToolbox and PHP 7.4 or greater is installed.

## Usage

Here is a basic usage example:

```php
<?php

declare(strict_types=1);

include "vendor/autoload.php";

use pointybeard\PdfToolbox;

// Generate a report for input files using profile
PdfToolbox\PdfToolbox::process(
    'test_profile.xml',
    ['test.pdf', 'test2.pdf'],
    [
        'report' => ['VARDUMP', 'ERROR,WARNING', 'PATH=./report/testreport.json'],
        'a',
    ],
    $o,
    $e
);

var_dump($o, $e);
```

See `pdfToolbox --help` on the command line to see help information for each of the options it supports.

## Support

If you believe you have found a bug, please report it using the [GitHub issue tracker](https://github.com/pointybeard/pdftoolbox/issues),
or better yet, fork the library and submit a pull request.

## Contributing

We encourage you to contribute to this project. Please check out the [Contributing documentation](https://github.com/pointybeard/pdftoolbox/blob/master/CONTRIBUTING.md) for guidelines about how to get involved.

## License

"PHP Wrapper for callas pdfToolbox" is released under the [MIT License](http://www.opensource.org/licenses/MIT).
