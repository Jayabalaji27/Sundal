# [YooKassa API SDK](../home.md)

# Class: \YooKassa\Common\Exceptions\ForbiddenException
### Namespace: [\YooKassa\Common\Exceptions](../namespaces/yookassa-common-exceptions.md)
---
**Summary:**

Секретный ключ или OAuth-токен верный, но не хватает прав для совершения операции.


---
### Constants
| Visibility | Name | Flag | Summary |
| ----------:| ---- | ---- | ------- |
| public | [HTTP_CODE](../classes/YooKassa-Common-Exceptions-ForbiddenException.md#constant_HTTP_CODE) |  |  |

---
### Properties
| Visibility | Name | Flag | Summary |
| ----------:| ---- | ---- | ------- |
| public | [$retryAfter](../classes/YooKassa-Common-Exceptions-ApiException.md#property_retryAfter) |  |  |
| public | [$type](../classes/YooKassa-Common-Exceptions-ApiException.md#property_type) |  |  |
| protected | [$responseBody](../classes/YooKassa-Common-Exceptions-ApiException.md#property_responseBody) |  |  |
| protected | [$responseHeaders](../classes/YooKassa-Common-Exceptions-ApiException.md#property_responseHeaders) |  |  |

---
### Methods
| Visibility | Name | Flag | Summary |
| ----------:| ---- | ---- | ------- |
| public | [__construct()](../classes/YooKassa-Common-Exceptions-ForbiddenException.md#method___construct) |  | Constructor. |
| public | [getErrorCode()](../classes/YooKassa-Common-Exceptions-ApiException.md#method_getErrorCode) |  |  |
| public | [getErrorDescription()](../classes/YooKassa-Common-Exceptions-ApiException.md#method_getErrorDescription) |  |  |
| public | [getErrorId()](../classes/YooKassa-Common-Exceptions-ApiException.md#method_getErrorId) |  |  |
| public | [getErrorParameter()](../classes/YooKassa-Common-Exceptions-ApiException.md#method_getErrorParameter) |  |  |
| public | [getResponseBody()](../classes/YooKassa-Common-Exceptions-ApiException.md#method_getResponseBody) |  |  |
| public | [getResponseHeaders()](../classes/YooKassa-Common-Exceptions-ApiException.md#method_getResponseHeaders) |  |  |
| protected | [parseErrorResponse()](../classes/YooKassa-Common-Exceptions-ApiException.md#method_parseErrorResponse) |  |  |

---
### Details
* File: [lib/Common/Exceptions/ForbiddenException.php](../../lib/Common/Exceptions/ForbiddenException.php)
* Package: Default
* Class Hierarchy:  
  * [\Exception](\Exception)
  * [\YooKassa\Common\Exceptions\ApiException](../classes/YooKassa-Common-Exceptions-ApiException.md)
  * \YooKassa\Common\Exceptions\ForbiddenException

---
## Constants
<a name="constant_HTTP_CODE" class="anchor"></a>
###### HTTP_CODE
```php
HTTP_CODE = 403
```



---
## Properties
<a name="property_retryAfter"></a>
#### public $retryAfter : mixed
---
**Type:** <a href="../mixed"><abbr title="mixed">mixed</abbr></a>

**Details:**
* Inherited From: [\YooKassa\Common\Exceptions\ApiException](../classes/YooKassa-Common-Exceptions-ApiException.md)


<a name="property_type"></a>
#### public $type : mixed
---
**Type:** <a href="../mixed"><abbr title="mixed">mixed</abbr></a>

**Details:**
* Inherited From: [\YooKassa\Common\Exceptions\ApiException](../classes/YooKassa-Common-Exceptions-ApiException.md)


<a name="property_responseBody"></a>
#### protected $responseBody : ?string
---
**Type:** <a href="../?string"><abbr title="?string">?string</abbr></a>

**Details:**
* Inherited From: [\YooKassa\Common\Exceptions\ApiException](../classes/YooKassa-Common-Exceptions-ApiException.md)


<a name="property_responseHeaders"></a>
#### protected $responseHeaders : array
---
**Type:** <a href="../array"><abbr title="array">array</abbr></a>

**Details:**
* Inherited From: [\YooKassa\Common\Exceptions\ApiException](../classes/YooKassa-Common-Exceptions-ApiException.md)



---
## Methods
<a name="method___construct" class="anchor"></a>
#### public __construct() : mixed

```php
public __construct(mixed $responseHeaders = [], mixed $responseBody = &#039;&#039;) : mixed
```

**Summary**

Constructor.

**Details:**
* Inherited From: [\YooKassa\Common\Exceptions\ForbiddenException](../classes/YooKassa-Common-Exceptions-ForbiddenException.md)

##### Parameters:
| Type | Name | Description |
| ---- | ---- | ----------- |
| <code lang="php">mixed</code> | responseHeaders  | HTTP header |
| <code lang="php">mixed</code> | responseBody  | HTTP body |

**Returns:** mixed - 


<a name="method_getErrorCode" class="anchor"></a>
#### public getErrorCode() : ?string

```php
public getErrorCode() : ?string
```

**Details:**
* Inherited From: [\YooKassa\Common\Exceptions\ApiException](../classes/YooKassa-Common-Exceptions-ApiException.md)

**Returns:** ?string - 


<a name="method_getErrorDescription" class="anchor"></a>
#### public getErrorDescription() : ?string

```php
public getErrorDescription() : ?string
```

**Details:**
* Inherited From: [\YooKassa\Common\Exceptions\ApiException](../classes/YooKassa-Common-Exceptions-ApiException.md)

**Returns:** ?string - 


<a name="method_getErrorId" class="anchor"></a>
#### public getErrorId() : ?string

```php
public getErrorId() : ?string
```

**Details:**
* Inherited From: [\YooKassa\Common\Exceptions\ApiException](../classes/YooKassa-Common-Exceptions-ApiException.md)

**Returns:** ?string - 


<a name="method_getErrorParameter" class="anchor"></a>
#### public getErrorParameter() : ?string

```php
public getErrorParameter() : ?string
```

**Details:**
* Inherited From: [\YooKassa\Common\Exceptions\ApiException](../classes/YooKassa-Common-Exceptions-ApiException.md)

**Returns:** ?string - 


<a name="method_getResponseBody" class="anchor"></a>
#### public getResponseBody() : ?string

```php
public getResponseBody() : ?string
```

**Details:**
* Inherited From: [\YooKassa\Common\Exceptions\ApiException](../classes/YooKassa-Common-Exceptions-ApiException.md)

**Returns:** ?string - 


<a name="method_getResponseHeaders" class="anchor"></a>
#### public getResponseHeaders() : string[]

```php
public getResponseHeaders() : string[]
```

**Details:**
* Inherited From: [\YooKassa\Common\Exceptions\ApiException](../classes/YooKassa-Common-Exceptions-ApiException.md)

**Returns:** string[] - 


<a name="method_parseErrorResponse" class="anchor"></a>
#### protected parseErrorResponse() : string

```php
protected parseErrorResponse(mixed $responseBody) : string
```

**Details:**
* Inherited From: [\YooKassa\Common\Exceptions\ApiException](../classes/YooKassa-Common-Exceptions-ApiException.md)

##### Parameters:
| Type | Name | Description |
| ---- | ---- | ----------- |
| <code lang="php">mixed</code> | responseBody  |  |

**Returns:** string - 



---

### Top Namespaces

* [\YooKassa](../namespaces/yookassa.md)

---

### Reports
* [Errors - 0](../reports/errors.md)
* [Markers - 0](../reports/markers.md)
* [Deprecated - 33](../reports/deprecated.md)

---

This document was automatically generated from source code comments on 2025-07-01 using [phpDocumentor](http://www.phpdoc.org/)

&copy; 2025 YooMoney