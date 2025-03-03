# Quick filters Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](http://keepachangelog.com/) and this project adheres to [Semantic Versioning](http://semver.org/).

## 3.0.3 - 2025-02-22
### Fixed
- Fixed bug with error occurring when adding filters to asset lists

## 3.0.2 - 2024-11-05
### Fixed
- Fixed getSections bug

## 3.0.0 - 2024-10-14
### Added
- Added Craft CMS 5 support

## 2.3.7 - 2024-02-21

### Added
- Added an option to use dropdown filters in ajax mode (filters using entry fields only)

## 2.3.6 - 2024-02-10

### Added
- Added an option to use single datepicker (defining specific date) in date filters, instead two datepickers (defining date range)

### Fixed
- Fixed the bug with date filters not selecting date which was the same as end date of range, if this date had hour later than 00:00

## 2.3.3 - 2024-02-08
### Fixed
- Fixed the error with dropdown filter having no options if it used entry field with channel section enabled for selection
- Fixed the error with options sorting setting not showing in the filter settings

## 2.3.2 - 2023-12-07
### Fixed
- Fixed the JS errors

## 2.3.1 - 2023-12-06
### Fixed
- Fixed the bug with empty entry dropdown filters
- Fixed the bug with db query being executed before Craft is fully initialized

## 2.3.0 - 2023-11-12
### Added
- Dropdown filters options can be now sorted alphabetically

## 2.2.2 - 2023-11-07
### Fixed
- Fixd bug when sometimes filters JS was not initialized
### Changed
- Changed the look of filter settings link

## 2.2.1 - 2023-02-21
### Fixed
- Filters now work properly in element selection modals

## 2.2.0 - 2022-10-21
### Added
- Added Colour Swatches plugin support
- Added "Has any value" and "Is empty" options to dropdowns
### Changed
- Styling improvements of dropdown widgets
- Date range filter now uses Craft datepicker

## 2.1.0 - 2022-08-15
### Added
- It is not possible to filter by entry type
- Added support for preparse field
- Relation type dropdown filters now display enabled/disabled status of elements
### Fixed
- Fixed size of button clearing value of filter

## 2.0.2 - 2022-06-24
### Added
- Fixed bug with optgroup in dropdown causing errors when used in filter

## 2.0.0 - 2022-05-28
### Added
- Added Craft CMS 4 support

## 1.0.0 - 2022-03-15
### Added
- Initial release
