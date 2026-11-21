# Changelog

All notable changes to this project are documented in this file.

The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/).

## [0.2.0] - Unreleased

### Added

- Mutation testing with Infection runs as the CI job `Mutation tests`. The job `CI passed` requires it. `make mutation` runs it locally, in the PHP 8.5 image of the CI. The command inside the image is `composer mutation`.

### Changed

- `PublicResponseWriter` stores a cookie without `Path`, or with an empty `Path`, with an empty path. Before, it stored `/`. symfony now sends such a cookie without `Path`. Before, such a cookie applied to the whole site. Now it applies only to the directory of the request URI. A cookie that must apply to the whole site needs `Path=/`. The difference `roundtrip/cookie-default-path` no longer exists.
- `composer.json` requires `php-64bit`.
- `PublicResponseWriter` throws `ConversionFailed` for a header with another name that would be sent as `Set-Cookie`. That is a name that symfony stores as `Set-Cookie`, for example `Set_Cookie`, and a name whose part before the first `:` is such a name, for example `Set-Cookie:x`. Before, the writer left a name such as `Set_Cookie` out without an error. Before, it wrote a name such as `Set-Cookie:x` as a header, and symfony sent it as `Set-Cookie`.
- `Expires` is read with the algorithm of RFC 6265 section 5.1.1, in the new class `Internal\CookieDate`. The parser no longer uses the date parser of PHP. The time is read as UTC, and a zone no longer moves it: `Wed, 21 Oct 2015 08:28:00 +0100` now gives 08:28 UTC. A number before the year can be read as the year: `Wed Oct 21 07:28:00 +0000 2015` now gives a date in the year 2000. A value without a year can take the year from a zone: `Wed, 21 Oct 08:28:00 +0000` now gives a date in the year 2000. A relative word such as `+5 years` no longer adds five years, and a weekday that does not match the date no longer moves the date. A number in such a word can still fill the day or the year when that field is empty. A day that does not exist, such as 31 February, and a year before 1601 are not a date, and the value is ignored.

## [0.1.0] - Unreleased

### Added

- `RequestConverter`: converts an `sfWebRequest` to a PSR-7 server request.
- `ResponseConverter`: converts an `sfWebResponse` to a PSR-7 response.
- `ResponseWriter` and `PublicResponseWriter`: write a PSR-7 response into an existing `sfWebResponse`. The writer takes an optional time source for `Max-Age`.
- `BridgeException` and `ConversionFailed`: everything that the bridge throws implements `BridgeException`.
- Header mapping of the request converter: every `HTTP_` entry is a header; a non-empty `CONTENT_TYPE`, `CONTENT_LENGTH` or `CONTENT_MD5` wins over its `HTTP_` entry; `Authorization` comes from the first of `HTTP_AUTHORIZATION`, `REDIRECT_HTTP_AUTHORIZATION`, `PHP_AUTH_USER` with `PHP_AUTH_PW`, and `PHP_AUTH_DIGEST`.
- The list of documented differences after a round trip (`roundtrip/header-name-normalised`, `roundtrip/header-values-joined`, `roundtrip/content-type-charset`, `roundtrip/protocol-version`, `roundtrip/cookie-same-name`, `roundtrip/cookie-max-age`, `roundtrip/cookie-default-path`, `roundtrip/cookie-unknown-attribute`, `roundtrip/reason-phrase-default`, `roundtrip/body-new-stream`, `roundtrip/no-content-header-only`) in the README.
