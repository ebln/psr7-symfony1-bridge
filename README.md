# psr7-symfony1-bridge

Converts between symfony1 web objects and PSR-7 messages.

## What it is

The bridge has three public classes and two exception types:

- `RequestConverter` converts an `sfWebRequest` to a PSR-7 `ServerRequestInterface`.
- `ResponseConverter` converts an `sfWebResponse` to a PSR-7 `ResponseInterface`.
- `PublicResponseWriter` writes a PSR-7 response into an existing `sfWebResponse`. It implements the interface `ResponseWriter`.
- `BridgeException` is the interface of everything that the bridge throws. `ConversionFailed` implements it.

These are converters, not adapters. No class of the bridge implements a PSR-7 or PSR-17 interface. The PSR-7 objects come from the PSR-7 implementation that you choose.

The two objects are independent after a conversion. Every converter reads everything during the call and keeps no reference to either object. A later change to the source is not visible in the result.

## Requirements

- PHP 7.4, or PHP 8.1 and later 8.x versions (`composer.json` declares `^7.4 || ^8.1`).
- 64-bit PHP. `composer.json` declares `php-64bit`, so Composer refuses the installation on 32-bit PHP. The latest expiry time of a cookie, `253402300799`, does not fit in a 32-bit integer.
- `psr/http-message` 1.1 or 2.0, and `psr/http-factory` 1.0.
- Any PSR-17 implementation. It supplies the factories for server requests, responses, streams and uploaded files. The tests of the bridge run with `guzzlehttp/psr7` and with `nyholm/psr7`.
- `friendsofsymfony1/symfony1` from version 1.5.23. The package does not require it, because your project provides symfony1. It declares `conflict` for `friendsofsymfony1/symfony1` below 1.5.23, so Composer refuses an older version.

## Installation

```sh
composer require ebln/psr7-symfony1-bridge
```

Install a PSR-17 implementation as well, if your project has none, for example:

```sh
composer require guzzlehttp/psr7
```

## Usage

This is an example of a helper in a project:

```php
use ebln\Symfony1\Psr7Bridge\PublicResponseWriter;
use ebln\Symfony1\Psr7Bridge\RequestConverter;
use ebln\Symfony1\Psr7Bridge\ResponseConverter;

$factory = new \GuzzleHttp\Psr7\HttpFactory();          // or any other PSR-17 implementation
$requests = new RequestConverter($factory, $factory, $factory);
$responses = new ResponseConverter($factory, $factory);
$writer = new PublicResponseWriter();

$psrRequest = $requests->convert($sfRequest);
$psrResponse = $responses->convert($sfResponse);

$result = $handler->handle($psrRequest, $psrResponse);   // the project's own code

$writer->write($result, $sfResponse);
```

`$sfRequest` is an `sfWebRequest`, `$sfResponse` is an `sfWebResponse`, and `$handler` is code of your project that takes a PSR-7 request and response and returns a PSR-7 response.

`RequestConverter` takes a server request factory, a stream factory and an uploaded file factory. `ResponseConverter` takes a response factory and a stream factory. `PublicResponseWriter` takes an optional time source, see [The time source](#the-time-source).

## What each converter reads and writes

The converters and the writer use public methods of the symfony1 classes only.

### Request: `sfWebRequest` to a server request

| Part | Source | Result |
|---|---|---|
| Method | `getMethod()` | The method that symfony resolved, as a string. |
| URI | `getUri()` | Passed as a string to `createServerRequest()`. A value that the PSR-7 implementation rejects makes `convert()` throw `ConversionFailed`. A request without a host always throws. The bridge invents no default host. |
| Server params | `getPathInfoArray()` | Passed unchanged. This is `$_SERVER` or `$_ENV`, depending on the symfony option `path_info_array`. |
| Headers | `getPathInfoArray()` | The bridge maps the entries itself, see below. It does not use `getHttpHeader()`, because that method removes backslashes. |
| Protocol version | `SERVER_PROTOCOL` | `HTTP/1.0` gives `1.0`. A value of the form `HTTP/2` or `HTTP/2.0` gives `2` or `2.0`. A missing or malformed value gives `1.1`. |
| Body | `getContent()` | A stream from `createStream()` that is positioned at the start. When symfony returns `false`, the stream is empty. |
| Query params | `getGetParameters()` | Passed unchanged. |
| Parsed body | `getPostParameters()` | Set only when the media type of the `Content-Type` header is `application/x-www-form-urlencoded` or `multipart/form-data`. The comparison ignores case and parameters such as `charset`. In every other case the parsed body is `null`. |
| Uploaded files | `getFiles()` | A tree of the same shape, with an `UploadedFileInterface` for each file. The tree can have any depth. `size` and `error` are cast to integers. `name` and `type` can be missing. For an upload with an error other than `UPLOAD_ERR_OK`, the bridge does not open the temporary file, and `getError()` returns the error code. |
| Cookie params | `$_COOKIE` | Passed unchanged. `sfWebRequest::getCookie()` reads the same array, and symfony1 has no method that lists all cookies. |
| Attributes | none | The result has no attributes. |

A value in the file tree that is neither an array nor a file entry stays unchanged in the result. `guzzlehttp/psr7` reports it with a deprecation notice.

How the headers are mapped:

1. Every key that starts with `HTTP_` becomes a header. The prefix is removed, `_` becomes `-`, and the name is lower case.
2. `CONTENT_TYPE`, `CONTENT_LENGTH` and `CONTENT_MD5` become headers when they are not empty. They win over `HTTP_CONTENT_TYPE`, `HTTP_CONTENT_LENGTH` and `HTTP_CONTENT_MD5`.
3. For `Authorization`, the first match wins: `HTTP_AUTHORIZATION`, `REDIRECT_HTTP_AUTHORIZATION`, `PHP_AUTH_USER` with `PHP_AUTH_PW` as `Basic ...`, and `PHP_AUTH_DIGEST`. An empty `HTTP_AUTHORIZATION` or `REDIRECT_HTTP_AUTHORIZATION` is not used as the source. Rule 1 still maps an empty `HTTP_AUTHORIZATION` to an empty `Authorization` header, unless a later source matches.
4. Each header has one value. The bridge never splits a value at commas.
5. A scalar value is cast to a string. A value that is not a scalar, for example an array or `null`, is skipped.
6. Spaces and tabs at both ends of a value are removed.

### Response writer: a PSR-7 response to an `sfWebResponse`

The target is an existing `sfWebResponse` that you pass in.

| Part | Rule |
|---|---|
| Existing state | The writer writes on top of it. It removes no header and no cookie. A header of the same name is replaced. See [The writer writes on top](#the-writer-writes-on-top). |
| Status | `setStatusCode()` with the code and the reason phrase of the PSR-7 response. The phrase is always passed, also when it is empty. |
| Status 204 | `setHeaderOnly(true)`. The writer never sets it back to `false`. |
| Headers | `Set-Cookie` is not written as a header. A header with another name that would be sent as `Set-Cookie` fails with `ConversionFailed`. That is a name that symfony stores as `Set-Cookie`, for example `Set_Cookie`, and a name whose part before the first `:` is such a name, for example `Set-Cookie:x`. For every other name the writer joins the values with `, ` and calls `setHttpHeader()` once. Symfony normalises the name: `CONTENT-type` becomes `Content-Type`. For a `text/*` type and for a type that ends in `xml` without a charset, symfony adds `; charset=` and its `charset` option. |
| Cookies | Every `Set-Cookie` value is parsed and written with `setCookie()`, see below. |
| Body | `setContent()` with the body as a string. The writer reads the stream from the start. It does not close the stream. |
| Protocol version | Not written. The version stays the value of the `http_protocol` option of the `sfWebResponse`. |

How a `Set-Cookie` value is parsed:

- The name and the value are split at the first `=`. A cookie without `=` has an empty value. The value is not decoded. White space around the name and the value is removed, as PHP `trim()` does it: space, tab, CR, LF, NUL and vertical tab.
- Attribute names are compared without case. An unknown attribute is ignored. When an attribute is repeated, the last one wins.
- `Max-Age` takes precedence over `Expires`, as RFC 6265 says. `Max-Age` is added to the current time, see [The time source](#the-time-source). A `Max-Age` of 0 or less gives the expiry time 1.
- `Expires` is read with the algorithm of RFC 6265 section 5.1.1. It needs a time (`h:m:s`), a day of the month, a month name and a year of two to four digits (`2*4DIGIT`), in any order. Any other value is ignored, for example `now`, `+1 day`, `-1` or `1445412480`. The parts of the value are read from left to right. Each part fills the first of the four fields (time, day, month, year) that it fits and that is still empty. A part that fits no empty field is ignored, for example `Wed` or `GMT`. The time is read as UTC, so a zone does not move it: `Wed, 21 Oct 2015 08:28:00 +0100` is 08:28 UTC. A number before the year can be read as the year: `Wed Oct 21 07:28:00 +0000 2015` is a date in the year 2000. A year from 70 to 99 is read as 1970 to 1999, and a year from 0 to 69 as 2000 to 2069. These are not a date: a year before 1601, a day of the month of 0 or above 31, an hour above 23, a minute or a second above 59, and a day that the month does not have, such as 31 April. A date before 1970 gives the expiry time 1.
- `Path` absent or empty gives an empty path. symfony then sends the cookie without `Path`, and the browser uses the directory of the request URI. `Domain` absent gives an empty string.
- `Secure` and `HttpOnly` are flags. `SameSite` is passed as written, and is an empty string when absent.
- When two cookies have the same name, the last one wins. `sfWebResponse::setCookie()` keeps one cookie for each name.

### Response: an `sfWebResponse` to a PSR-7 response

| Part | Source | Result |
|---|---|---|
| Status | `getStatusCode()`, `getStatusText()` | The code as an integer and the text as a string, both passed to `createResponse()`. The stored code is cast to an integer as PHP casts it, so `'404 '` (with a trailing space) and `'404abc'` give 404. Text without a leading number gives 0. A stored text of `null` gives an empty string. |
| Headers | `getHttpHeaders()` | One value for each name, not split at commas. The names are as symfony stores them, for example `X-Psr-7-Default`. A scalar value is cast to a string. |
| Cookies | `getCookies()` | One `Set-Cookie` header for each cookie, with `Expires`, `Path`, `Domain`, `Secure`, `HttpOnly` and `SameSite` as stored. An attribute that is empty is left out. There is no `Max-Age`. |
| Body | `getContent()` | A stream from `createStream()` with the content as a string. A value that is not a string is cast; `null` gives an empty string. |
| Protocol version | `getOptions()['http_protocol']` | `HTTP/1.0` gives `1.0`, `HTTP/1.1` gives `1.1`. |
| Header-only flag | none | The converter does not read `isHeaderOnly()`. The response to a `HEAD` request, or after a 204, converts with its full body. |
| Values added at send time | none | Symfony adds a default `Content-Type` when it sends the response. The converter does not include it. It reports the state of the object. |

## Requests of real deployments

- **Host of the URI.** `sfWebRequest::getUri()` takes the host from the header `X-Forwarded-Host` when the symfony option `trust_proxy` is on. It is on by default. When the header holds several values, symfony uses the last one. The host of the PSR-7 URI and the `host` header can then differ. A client controls this host, unless a proxy removes the header. Keep this in mind when you build links from the URI. When `REQUEST_URI` starts with `http`, the host comes from `REQUEST_URI` instead, see the next item.
- **Scheme and port of the URI.** When `REQUEST_URI` starts with `http`, symfony uses it as the whole URI. Scheme, host and port then come from `REQUEST_URI`, and the headers and `trust_proxy` described here do not apply. `REQUEST_URI` holds the request target that the client sent. When the web server passes an absolute target on unchanged, for example from the request line `GET http://other.example/ HTTP/1.1`, the client controls scheme, host and port, also with `trust_proxy` off. Otherwise the scheme comes from the server value `HTTPS`. When the symfony option `trust_proxy` is on, the headers `X-Forwarded-Proto: https` and `Ssl-Https: on` also give the scheme `https`. `Ssl-Https: on` and the server value `HTTPS` change the scheme. Symfony then takes the port from the host when the host names one, else from its option `https_port`, else from the server value `SERVER_PORT`, and leaves out only port 443. The host is the `Host` header, or `X-Forwarded-Host` as the previous item describes. A request to port 80 gives `https://example.org:80/` when `https_port` is not set. The header `X-Forwarded-Proto: https` changes the port as well, with `trust_proxy` on and off: symfony removes the port, or uses its option `https_port` when that is set. With `trust_proxy` off, a client can still turn `http://example.org:8080/` into `http://example.org/`. A client controls these headers, unless a proxy removes them.
- **Method.** Symfony knows the methods GET, POST, PUT, PATCH, DELETE, HEAD and OPTIONS. For every other method, for example `PROPFIND`, `getMethod()` returns `GET`, and the converted request has the method `GET`. The server params keep the original method. A `POST` request with the parameter `sf_method`, in the body or in the query, has the method of that parameter. Symfony removes `sf_method` from the parameters.
- **Error messages.** When the bridge wraps an exception of the PSR-7 implementation, the message of the `ConversionFailed` ends with the message of that exception. Such a message can contain data of the request. `guzzlehttp/psr7` repeats a header value that it rejects. Most requests that cannot be converted are client errors: answer them with status 400. One case is a state of the server: an upload whose temporary file cannot be opened, although the upload has no error. Do not put the message into the response in either case.
- **Memory.** Every conversion copies the body as a PHP string, in the request converter, in the response converter and in the writer. A large body needs its size in memory.

## The time source

The writer needs the current time for one purpose: to turn `Max-Age` into the expiry time that symfony stores. The constructor of `PublicResponseWriter` takes an optional callable that returns a `\DateTimeInterface`. Without the argument the writer uses the system time.

The writer asks the time source at most once for each call of `write()`. It asks only when a cookie has a `Max-Age` greater than 0.

A project with a PSR-20 clock passes the method `now` of the clock:

```php
$writer = new PublicResponseWriter([$clock, 'now']);
```

`$clock` is an object that implements `Psr\Clock\ClockInterface` from the package `psr/clock`. This library does not require `psr/clock`. If you use this example, install that package, or use any other callable that returns a `\DateTimeInterface`. In a test you can pass a closure that returns a fixed time.

## The writer writes on top

`PublicResponseWriter` does not clear the target. A header or a cookie that is in the `sfWebResponse` stays there, unless the PSR-7 response has a header or a cookie of the same name.

This has a consequence for a project that converts its `sfWebResponse`, changes the copy and writes it back into the same object. When the changed copy no longer has a header or a cookie, for example after `withoutHeader()`, that header or cookie stays in the `sfWebResponse`.

A cookie that symfony code set with `setHttpHeader('Set-Cookie', ...)` is reported by the converter as a `Set-Cookie` value. The writer parses that value and stores a cookie. After the write into the same object, the header is still there and the cookie exists as well, so symfony sends the cookie twice. Use `setCookie()` in your own code to avoid this.

## Differences after a round trip

A round trip writes a PSR-7 response into an empty `sfWebResponse` and converts that object back. "The same content" means the same parsed content, not the same text. These differences remain.

| Identifier | Difference |
|---|---|
| `roundtrip/header-name-normalised` | Symfony normalises header names. `ETag` becomes `Etag`, `X-PSR-7` becomes `X-Psr-7`, and `_` becomes `-`. `X_Foo` and `X-Foo` become one header, and the later one replaces the earlier one. |
| `roundtrip/header-values-joined` | Several values of one name become one value, joined with `, `. |
| `roundtrip/content-type-charset` | Symfony appends `; charset=` and its `charset` option (default `utf-8`) to a `text/*` type and to a type that ends in `xml`. A written `Content-Type` with its own charset changes that option. |
| `roundtrip/protocol-version` | The writer does not write the version. The conversion back returns the option `http_protocol` of the `sfWebResponse`. |
| `roundtrip/cookie-same-name` | Of several cookies with one name, the last one stays. |
| `roundtrip/cookie-max-age` | Every `Max-Age` becomes an expiry time, because the symfony cookie stores only `expire`. The conversion back writes `Expires` and no `Max-Age`. With a fixed time source the expiry time is exact. |
| `roundtrip/cookie-unknown-attribute` | Unknown attributes are dropped. |
| `roundtrip/reason-phrase-default` | An empty reason phrase depends on the PSR-7 implementation. `guzzlehttp/psr7` gives an empty phrase its default phrase, for a code that has one, when it creates the response (`OK` for 200). `nyholm/psr7` keeps it empty. When the same implementation builds the source and the result, the phrase does not change: `OK` stays `OK` with guzzle and an empty phrase stays empty with nyholm. |
| `roundtrip/body-new-stream` | The body is a new stream with the same string content. |
| `roundtrip/no-content-header-only` | Status 204 sets the header-only flag of the `sfWebResponse`. A later write with another status does not reset it. |

## Further documented differences

### A request body that holds only whitespace arrives empty

Identifier: `request/whitespace-only-body-is-empty`.

`sfWebRequest::getContent()` returns `false` for a body that holds only whitespace. The bridge converts `false` to an empty stream. Symfony decides this while it reads `php://input`, so a test of the bridge cannot reach it.

### Validation belongs to the PSR-7 implementation

Apart from the five cases under [Errors](#errors), the bridge does not check URIs, status codes or header values itself. It passes them to the PSR-7 implementation and wraps an exception of the implementation in `ConversionFailed`. The two implementations in the tests differ:

| Input | `guzzlehttp/psr7` | `nyholm/psr7` 1.8 |
|---|---|---|
| Request with the host `foo bar` | Rejected. | Accepted. |
| Status code 99 or 600 | Rejected. | Accepted. |
| Stored status code that is text without a leading number (it arrives as 0) | Rejected. | Accepted, with the code 0. |
| URI without a host, such as `http:///` | Rejected. | Rejected. |
| Header value with a line break | Rejected. | Rejected. |

### Cookies

- The writer limits an expiry time to 9999-12-31 23:59:59 UTC (`253402300799`), because PHP cannot send a later one.
- The writer ignores a `Max-Age` or an `Expires` that is not valid. A valid earlier value of the same attribute stays (RFC 6265).
- The writer stores a cookie deletion (`Max-Age` of 0 or less, or an `Expires` before 1970) with the expiry time 1. The value 0 would mean a session cookie.
- The converter reports the stored state. It does not apply the rules that PHP applies when it sends a cookie. A negative stored expiry time is written as the date it names, for example `Expires=Wed, 31 Dec 1969 23:59:55 GMT` for -5. An expiry time of `null`, 0 or an empty string gives no `Expires`. A stored deletion with an empty value is written as `a=; Expires=Thu, 01 Jan 1970 00:00:01 GMT`. A cookie that is stored with an empty value and without an expiry time is written as `a=; Path=/`. PHP does not send that as an empty cookie: when the value is empty, `setrawcookie()` sends a deletion (the value `deleted` and an expiry time in the past), as the documentation of `setcookie()` describes.

### Content type

Symfony keeps the charset of an earlier write. After a write with `application/json; charset=iso-8859-1`, a write with `text/html` stores `text/html; charset=iso-8859-1`.

### Status code and reason phrase

The converter passes the stored status code as an integer to the PSR-7 implementation, which validates it, see the table above. For an empty reason phrase, `guzzlehttp/psr7` supplies its default phrase when the code has one, and `nyholm/psr7` keeps the phrase empty.

### The external PSR-7 test suite

The suite `php-http/psr7-integration-tests` runs on converted objects with both implementations. In the request suite of both implementations one test is skipped, because a converted symfony request always has a host (`testUriPreserveHost_NoHost_Host`).

For `guzzlehttp/psr7`, five more tests are skipped. A plain `guzzlehttp/psr7` object fails them too:

- `testWithHeaderInvalidArguments`: guzzle accepts an empty array and `false` as a header value.
- `testWithAddedHeaderInvalidArguments`: the same for `withAddedHeader()`.
- `testGetParsedBodyInvalid`: guzzle accepts a scalar as parsed body.
- `testMethodIsCaseSensitive`: guzzle returns the method in upper case.
- `testGetRequestTargetInOriginFormNormalizesUriWithMultipleLeadingSlashesInPath`: guzzle keeps the leading slashes in the request target.

## Errors

Everything that the bridge throws implements `BridgeException`. All three public methods can throw `ConversionFailed`, which extends `\RuntimeException`. Catch the interface to handle every error of the bridge:

```php
try {
    $psrRequest = $requests->convert($sfRequest);
} catch (\ebln\Symfony1\Psr7Bridge\BridgeException $e) {
    // $e->getPrevious() holds the exception of the PSR-7 implementation, if there was one
}
```

The message of a `ConversionFailed` is the reason, followed by the message of the previous exception when there is one.

The bridge throws `ConversionFailed` by itself in five cases:

1. A request without a host (`RequestConverter`).
2. A `Set-Cookie` value without a cookie name (`PublicResponseWriter`), and a stored cookie without a name (`ResponseConverter`).
3. A cookie whose name, value, path, domain or `SameSite` holds a comma, a semicolon, a space, a tab, CR, LF, a vertical tab or a form feed. For a cookie stored in symfony, a name that holds `=` fails as well. This applies in both directions. PHP's `setrawcookie()` refuses these characters in the name, the value, the path and the domain when symfony sends the response: PHP 7.4 returns `false` with a warning and PHP 8 throws a `ValueError`. PHP sends a `SameSite` value unchanged, so a separator there would add other attributes. In the other direction, a stored value written unchanged could become a `Set-Cookie` line with other attributes. An example for the writer is the line `Set-Cookie: a=1, b=2`: its value `1, b=2` holds a comma and a space.
4. A time source that does not return a `\DateTimeInterface`.
5. A header name other than `Set-Cookie` that would be sent as `Set-Cookie` (`PublicResponseWriter`).

The bridge wraps in `ConversionFailed`, with the original exception as `previous`, every `\Exception` that the PSR-7 implementation throws and every `\Exception` that the time source throws. A body that cannot be read is wrapped in the same way.

When `write()` throws, the `sfWebResponse` is unchanged. The writer reads and checks everything before it writes.

## Not public API

Everything in the namespace `ebln\Symfony1\Psr7Bridge\Internal` is not public API. Those classes carry `@internal` and can change in any release. Use the classes and interfaces listed under [What it is](#what-it-is) only.

`ConversionFailed::because()` is internal as well. A project that throws a `ConversionFailed` itself, for example in its own `ResponseWriter`, uses the constructor: `new ConversionFailed($message, 0, $previous)`.

## Relation to `ebln/psr7-symfony1-adapter`

`ebln/psr7-symfony1-adapter` is a separate package with its own namespace. The two packages declare no `replace` or `conflict` against each other, and their classes do not share a namespace. A project can install both next to each other.
