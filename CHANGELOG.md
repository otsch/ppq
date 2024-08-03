# Changelog
All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [0.2.0] - 2024-08-03
### Added
* Error handling functionality. You can now add a `error_handler` in your config, like this: `['error_handler' => ['class' => MyErrorHandler::class, 'active' => true]`. The class needs to extend the new abstract class `Otsch\Ppq\AbstractErrorHandler`. In its `boot()` method you can register one or multiple error handlers via its own `registerHandler()` method. The handlers are automatically called with any uncaught exception or PHP warnings and errors (turned into `ErrorException`s) occuring during a PPQ job execution.

## [0.1.2] - 2022-07-13
### Fixed
* Fix identifying Sub-Processes that need to be killed, when cancelling a running Process.

## [0.1.1] - 2022-06-22
### Fixed
* When the new content of the ppq index file was shorter than the old content, it wrote the new content starting from the beginning of the file and the part of the old content that was longer than the new content, remained at the end of the file. Fixed it be truncating the file before writing the new content.

## [0.1.0] - 2022-06-21
