<?php

declare(strict_types=1);

/*
 * This file is part of the "PHP Wrapper for callas pdfToolbox" repository.
 *
 * Copyright 2021-2022 Alannah Kearney <hi@alannahkearney.com>
 *
 * For the full copyright and license information, please view the LICENCE
 * file that was distributed with this source code.
 */

namespace pointybeard\PdfToolbox;

use Exception;
use pointybeard\Helpers\Functions\Cli;
use pointybeard\PdfToolbox\Exceptions\PdfToolboxAssertionFailedException;
use pointybeard\PdfToolbox\Exceptions\PdfToolboxException;
use pointybeard\PdfToolbox\Exceptions\PdfToolboxExecutionFailedException;

class PdfToolbox
{
    // This is the name of the pdfToolbox executable
    public const EXECUTABLE_NAME = 'pdfToolbox';

    public const OPTION_TYPE_LONG = '--';

    public const OPTION_TYPE_SHORT = '-';

    // Supported options. This is a mirror of options available directly. See pdfToolbox --help for more details
    private static $options = ['visiblelayers', 'logexecution', 'trace_nosubfolders', 'trace', 'syntaxchecks', 'novariables', 'jobid', 'referencexobjectpath', 'maxpages', 'openpassword', 'addxmp', 'certify', 'incremental', 'hitsperpage', 'hitsperdoc', 'setvariablepath', 'setvariable', 'a' => ['aliasFor' => 'analyze'], 'analyze', 'nosummary', 'nohits', 'uncompressimg', 'licensetype', 'timeout_licenseserver', 'lsmessage', 'licenseserver', 'satellite_type', 'timeout_satellite', 'timeout_dispatcher', 'noshadowfiles', 'nolocal', 'endpoint', 'dist', 'timeout', 'customdict', 'l' => ['aliasFor' => 'language'], 'language', 'maxmemory', 'cachefolder', 'noprogress', 't' => ['aliasFor' => 'timestamp'], 'timestamp', 'topdf_noremotecontent', 'topdf_psepilogue', 'topdf_psprologue', 'password', 'topdf_parameter', 'topdf_psfontsonly', 'topdf_psaddfonts', 'topdf_ignore', 'p' => ['aliasFor' => 'pagerange'], 'pagerange', 'topdf_pdfsetting', 'topdf_useexcelpagelayout', 'topdf_screen', 'optimizepdf', 'nooptimization', 'o' => ['aliasFor' => 'outputfile'], 'outputfile', 'f' => ['aliasFor' => 'outputfolder'], 'outputfolder', 'w' => ['aliasFor' => 'overwrite'], 'overwrite', 's' => ['aliasFor' => 'suffix'], 'suffix', 'report'];

    // Make sure this class cannot be instanciated
    private function __construct()
    {
    }

    private static function runPdfWithArgs(string $args, string &$stdout = null, string &$stderr = null, int &$exitCode = null): void
    {
        // (guard) pdfToolbox is not installed or isn't in PATH
        self::assertInstalled();

        $command = sprintf('%s %s', Cli\which(self::EXECUTABLE_NAME), $args);

        try {
            Cli\run_command($command, $stdout, $stderr, $exitCode);
        } catch (Exception $ex) {

            // (guard) Error codes < 100 indicate successful operation
            if($exitCode < 100) {
                return;
            }

            throw new PdfToolboxExecutionFailedException($args, $stderr, $exitCode, 0, $ex);
        }
    }

    private static function assertInstalled(): void
    {
        if (null == Cli\which(self::EXECUTABLE_NAME)) {
            throw new PdfToolboxAssertionFailedException(self::EXECUTABLE_NAME.' executable cannot be located.');
        }
    }

    private static function assertOptionValid($option): void
    {
        if (null == self::getOption($option)) {
            throw new PdfToolboxAssertionFailedException("Unsupported option '{$option}' specified.");
        }
    }

    private static function assertFileExists($file): void
    {
        if (false == is_readable($file) || false == file_exists($file)) {
            throw new PdfToolboxAssertionFailedException("File '{$file}' does not exist or is not readable.");
        }
    }

    public static function version(): ?string
    {
        self::runPdfWithArgs('--version', $output);

        return $output;
    }

    public static function getOptions(): array
    {
        return self::$options;
    }

    private static function getOptionType(string $name): string
    {
        return 1 == strlen($name) ? self::OPTION_TYPE_SHORT : self::OPTION_TYPE_LONG;
    }

    private static function getOption($name, bool $resolveAlias = true)
    {

        // (guard) option doesn't exist
        if (false == in_array($name, self::$options) && false == array_key_exists($name, self::$options)) {
            return null;
        }

        // (guard) simple option with no additional properties
        if (false == array_key_exists($name, self::$options)) {
            return $name;
        }

        $o = self::$options[$name];

        return false == $resolveAlias || false == isset($o['aliasFor']) ? $o : self::getOption($o['aliasFor'], false);
    }

    private static function generateOptionKeyValueString(string $name, $value): string
    {
        // (guard) option name is invalid
        self::assertOptionValid($name);

        // This will give us either the short (-) or long (--) prefix to use later
        $type = self::getOptionType($name);

        // (guard) value is null
        if (null == $value) {
            return $type.$name;
        }

        return $type.sprintf(
            self::OPTION_TYPE_SHORT == $type
                ? '%s "%s"'
                : '%s="%s"',
            $name,
            $value
        );
    }

    public static function processString(string $profile, string $input, array $options = [], ?string &$output = null, ?string &$errors = null): bool
    {
        // Save the string contents to a tmp file then call self::process();
        $inputFile = tempnam(sys_get_temp_dir(), self::EXECUTABLE_NAME);

        // (guard) Unable to create a temporary file name
        if (false == $inputFile) {
            throw new PdfToolboxException('Unable to generate temporary file.');
        }

        // pdfToolbox requires a `.pdf` extension otherwise it will refuse to load the file.
        // So, rename the temp file to include the `.pdf` extension
        if (false == rename($inputFile, $inputFile .= '.pdf')) {
            throw new PdfToolboxException('Unable to generate temporary file. Failed to add .pdf extension.');
        }

        // (guard) Unable to save contents to temporary file
        if (false === file_put_contents($inputFile, $input)) {
            throw new PdfToolboxException("Unable to save input string to temporary file {$inputFile}.");
        }

        return self::process($profile, $inputFile, $options, $output, $errors);
    }

    public static function process(string $profile, $inputFiles, array $options = [], ?string &$output = null, ?string &$errors = null, ?int &$code = null): bool
    {
        $opts = [];
        foreach ($options as $name => $value) {
            // (guard) $name is numeric
            if (true == is_numeric($name)) {
                $name = $value;
                $value = null;
            }

            if (false == is_array($value)) {
                $value = [$value];
            }

            // This gives us support for multiple items with the same name. E.g. setvariable
            foreach ($value as $v) {
                $opts[] = self::generateOptionKeyValueString($name, $v);
            }
        }

        if (false == is_array($inputFiles)) {
            $inputFiles = [$inputFiles];
        }

        // (guard) profile doesnt exist
        self::assertFileExists($profile);

        // (guard) input file doesn't exist
        array_map('self::assertFileExists', $inputFiles);

        $command = sprintf(
            '%s %s %s', // <profile> <input files> [<input files> [...] ] <options>
            $profile,
            implode(' ', $inputFiles),
            implode(' ', $opts)
        );

        self::runPdfWithArgs($command, $output, $errors, $code);

        return true;
    }
}
