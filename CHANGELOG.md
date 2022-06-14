# Change Log
All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](http://keepachangelog.com/)
and this project adheres to [Semantic Versioning](http://semver.org/).

## [Unreleased]
### Changed 

- Dropped support for Magento 2.0

## [1.4.5] - 2022-06-02
### Fixed

- Fix Magento Framework version requirements in composer.json to make module possibly to require with M2.4.x

## [1.4.4] - 2022-05-06
### Added

- Disable password changing request if module is active
- Handle "There are no admin users to attempt login to." error more gracefully

## [1.4.3] - 2022-05-03
### Added 

- Support for Magento 2.4.x 

## [1.4.2] - 2019-03-28
### Fixed

- Allow the module to be installed on 2.3 (https://github.com/vaimo/module-admin-auto-login/issues/4)

## [1.4.1] - 2018-07-24
### Fixed

- Keep URL parameters on redirect ([by @roman-snitko](https://github.com/vaimo/module-admin-auto-login/pull/3))

## [1.4.0] - 2017-11-09
### Added

- Support for Magento 2.2

## [1.3.0] - 2017-05-08
### Added
- Public release
- Changelog
- Do not redirect back to dashboard after session expiration

[Unreleased]: https://github.com/vaimo/module-admin-auto-login/compare/v1.3.0...HEAD
