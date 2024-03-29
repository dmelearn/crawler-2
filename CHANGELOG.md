# Changelog

All notable changes to `dmelearn/crawler` will be documented in this file.

### Release

## 2.10.0 - 2023-08-22
- Support for Guzzle 7.x

## 2.9.0 - 2021-11-15
- Minimum PHP 7.2
- The hasBeenCrawled() takes an error message

## 2.8.0 - 2018-02-09
- Original Release
- Keep PHP 7.0 Support
- Alternative create function for sites with login
- The finishedCrawling() function now returns data from crawl

### Original `spatie/crawler` releases

## 2.7.1 - 2017-12-13
- allow symfony 4 crawler

## 2.7.0 - 2017-12-10
- added the ability to change the crawl queue

## 2.6.2 - 2017-12-10
- more performance improvements

## 2.6.1 - 2017-12-10
- performance improvements

## 2.6.0 - 2017-10-16
- add `CrawlSubdomains` profile

## 2.5.0 - 2017-09-27
- add crawl count limit

## 2.4.0 - 2017-09-21
- add depth limit

## 2.3.0 - 2017-09-21
- add JavaScript execution

## 2.2.1 - 2017-09-07
- fix deps for PHP 7.2

## 2.2.0 - 2017-08-03
- add `EmptyCrawlObserver`

## 2.1.2 - 2017-03-06
- refactor to make use of Symfony Crawler's `link` function

## 2.1.1 - 2017-03-03
- fix bugs around relative urls

## 2.1.0 - 2017-01-27
- add `CrawlInternalUrls`

## 2.0.7 - 2016-12-30
- make sure the passed client options are being used

## 2.0.6 - 2016-12-15
- second attempt to fix detection of redirects

## 2.0.5 - 2016-12-15
- fix detection of redirects

## 2.0.4 - 2016-12-15
- fix the default timeout of 5 seconds

## 2.0.3 - 2016-12-13
- set a default timeout of 5 seconds

## 2.0.2 - 2016-12-05
- fix for non responding hosts

## 2.0.1 - 2016-12-05
- fix for the accidental crawling of mailto-links

## 2.0.0 - 2016-12-05
- improve performance by concurrent crawling
- make it possible to determine on which url a url was found

## 1.3.1 - 2015-09-13
- Ignore `tel:` links when crawling

## 1.3.0 - 2015-08-18
- Added `path`, `segment` and `segments` functions to `Url`

## 1.2.3 - 2015-08-12
- Updated the required version of Guzzle to a secure version

## 1.2.2 - 2015-03-08
- Fixed a bug where the crawler would not take query strings into account

## 1.2.1 - 2015-03-01
- Fixed a bug where the crawler tries to follow JavaScript links

## 1.2.0 - 2015-12-21
- Add support for DomCrawler 3.x

## 1.1.1 - 2015-11-16
- Fix for normalizing relative links when using non-80 ports

## 1.1.0 - 2015-11-16
- Add support for custom ports

## 1.0.2 - 2015-11-05
- Lower required php version to 5.5

## 1.0.1 - 2015-11-03
- Make url's case sensitive

## 1.0.0 - 2015-11-03
- First release
