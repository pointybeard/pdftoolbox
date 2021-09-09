<?php

declare(strict_types=1);

/*
 * This file is part of the "PHP Wrapper for callas pdfToolbox" repository.
 *
 * Copyright 2021 Alannah Kearney <hi@alannahkearney.com>
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
    private const EXECUTABLE_NAME = 'pdfToolbox';

    private static $paths = [];

    // Supported options. This is a mirror of options available directly. See pdfToolbox --help for more details
    private static $options = [
        'visiblelayers',
        'logexecution',
        'trace_nosubfolders',
        'trace',
        'syntaxchecks',
        'novariables',
        'jobid',
        'referencexobjectpath',
        'maxpages',
        'openpassword',
        'addxmp',
        'certify',
        'incremental',
        'hitsperpage',
        'hitsperdoc',
        'setvariablepath',
        'setvariable',
        'analyze',
        'nosummary',
        'nohits',
        'uncompressimg',
        'licensetype',
        'timeout_licenseserver',
        'lsmessage',
        'licenseserver',
        'satellite_type',
        'timeout_satellite',
        'timeout_dispatcher',
        'noshadowfiles',
        'nolocal',
        'endpoint',
        'dist',
        'timeout',
        'customdict',
        'language',
        'maxmemory',
        'cachefolder',
        'noprogress',
        'timestamp',
        'topdf_noremotecontent',
        'topdf_psepilogue',
        'topdf_psprologue',
        'password',
        'topdf_parameter',
        'topdf_psfontsonly',
        'topdf_psaddfonts',
        'topdf_ignore',
        'pagerange',
        'topdf_pdfsetting',
        'topdf_useexcelpagelayout',
        'topdf_screen',
        'optimizepdf',
        'nooptimization',
        'outputfile',
        'outputfolder',
        'overwrite',
        'suffix',
        'report',
    ];

    // Make sure this class cannot be instanciated
    private function __construct()
    {
    }

    private static function runPdfToolboxWithArgs(string $args, string &$stdout = null, string &$stderr = null): void
    {
        // (guard) pdfToolbox is not installed or isn't in PATH
        self::assertPdfToolboxInstalled();

        $command = sprintf('%s %s', Cli\which(self::EXECUTABLE_NAME), $args);

        try {
            Cli\run_command($command, $stdout, $stderr, $exitCode);
        } catch (Exception $ex) {
            throw new PdfToolboxExecutionFailedException($args, $stderr, $exitCode, 0, $ex);
        }
    }

    private static function assertPdfToolboxInstalled(): void
    {
        if (null == Cli\which(self::EXECUTABLE_NAME)) {
            throw new PdfToolboxAssertionFailedException(self::EXECUTABLE_NAME.' executable cannot be located.');
        }
    }

    private static function assertOptionExists($option): void
    {
        if (false == in_array($option, self::$options)) {
            throw new PdfToolboxAssertionFailedException("Invalid option '{$option}' specified.");
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
        self::runPdfToolboxWithArgs('--version', $output);

        return $output;
    }

    public static function processString(string $profile, string $input, array $arguments = [], ?string &$output = null, ?string &$errors = null): bool
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

        return self::process($profile, $inputFile, $arguments, $output, $errors);
    }

    public static function process(string $profile, $inputFiles, array $arguments = [], ?string &$output = null, ?string &$errors = null): bool
    {
        $args = [];
        foreach ($arguments as $name => $value) {

            // (guard) option name is invalid
            self::assertOptionExists($name);

            if (is_numeric($name)) {
                $args[] = "--{$value}";

                continue;
            }

            if (false == is_array($value)) {
                $value = [$value];
            }

            $args[] = sprintf('--%s="%s"', $name, implode(' ', $value));
        }

        if (false == is_array($inputFiles)) {
            $inputFiles = [$inputFiles];
        }

        // (guard) profile doesnt exist
        self::assertFileExists($profile);

        // (guard) input file doesn't exist
        array_map('self::assertFileExists', $inputFiles);

        self::runPdfToolboxWithArgs(sprintf(
            '%s %s %s', // <profile> <input files> [<input files> [...] ] <args>
            $profile,
            implode(' ', $inputFiles),
            implode(' ', $args)
        ), $output, $errors);

        return true;
    }
}
