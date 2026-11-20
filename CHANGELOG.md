# Changelog

All notable changes to this project are documented in this file.

The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/).

## [0.1.0] - Unreleased

### Added

- `RequestConverter`: converts an `sfWebRequest` to a PSR-7 server request.
- `ResponseConverter`: converts an `sfWebResponse` to a PSR-7 response.
- `ResponseWriter` and `PublicResponseWriter`: write a PSR-7 response into an existing `sfWebResponse`. The writer takes an optional time source for `Max-Age`.
- `BridgeException` and `ConversionFailed`: everything that the bridge throws implements `BridgeException`.
- Header mapping of the request converter: every `HTTP_` entry is a header; a non-empty `CONTENT_TYPE`, `CONTENT_LENGTH` or `CONTENT_MD5` wins over its `HTTP_` entry; `Authorization` comes from the first of `HTTP_AUTHORIZATION`, `REDIRECT_HTTP_AUTHORIZATION`, `PHP_AUTH_USER` with `PHP_AUTH_PW`, and `PHP_AUTH_DIGEST`.
- The list of documented differences after a round trip (`roundtrip/header-name-normalised`, `roundtrip/header-values-joined`, `roundtrip/content-type-charset`, `roundtrip/protocol-version`, `roundtrip/cookie-same-name`, `roundtrip/cookie-max-age`, `roundtrip/cookie-default-path`, `roundtrip/cookie-unknown-attribute`, `roundtrip/reason-phrase-default`, `roundtrip/body-new-stream`, `roundtrip/no-content-header-only`) in the README.
