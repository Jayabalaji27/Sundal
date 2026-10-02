<?php

/*
 * The MIT License
 *
 * Copyright (c) 2025 "YooMoney", NBСO LLC
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 */

namespace YooKassa\Common\Exceptions;

use Exception;

/**
 * Неожиданный код ошибки.
 */
class ApiException extends Exception
{
    /**
     * @var string|null
     */
    protected ?string $responseBody = '';

    /**
     * @var string[]
     */
    protected array $responseHeaders = [];

    public mixed $type = null;

    public mixed $retryAfter = null;

    private ?string $errorId = null;
    private ?string $errorCode = null;
    private ?string $errorDescription = null;
    private ?string $errorParameter = null;

    /**
     * Constructor.
     *
     * @param string $message Error message
     * @param int $code HTTP status code
     * @param string[] $responseHeaders HTTP header
     * @param string|null $responseBody HTTP body
     */
    public function __construct(string $message = '', int $code = 0, array $responseHeaders = [], ?string $responseBody = '')
    {
        parent::__construct($message, $code);
        $this->responseHeaders = $responseHeaders;
        $this->responseBody = $responseBody;
    }

    /**
     * @return string[]
     */
    public function getResponseHeaders(): array
    {
        return $this->responseHeaders;
    }

    public function getResponseBody(): ?string
    {
        return $this->responseBody;
    }

    protected function parseErrorResponse(mixed $responseBody): string
    {
        $errorData = json_decode($responseBody, true);
        $message = '';

        if (isset($errorData['description'])) {
            $this->errorDescription = $errorData['description'];
            $message .= $errorData['description'] . '. ';
        }

        if (isset($errorData['code'])) {
            $this->errorCode = $errorData['code'];
            $message .= sprintf('Error code: %s. ', $errorData['code']);
        }

        if (isset($errorData['parameter'])) {
            $this->errorParameter = $errorData['parameter'];
            $message .= sprintf('Parameter name: %s. ', $errorData['parameter']);
        }

        if (isset($errorData['retry_after'])) {
            $this->retryAfter = $errorData['retry_after'];
        }

        if (isset($errorData['type'])) {
            $this->type = $errorData['type'];
        }

        if (isset($errorData['id'])) {
            $this->errorId = $errorData['id'];
        }

        return $message;
    }

    public function getErrorId(): ?string
    {
        return $this->errorId;
    }

    public function getErrorCode(): ?string
    {
        return $this->errorCode;
    }

    public function getErrorDescription(): ?string
    {
        return $this->errorDescription;
    }

    public function getErrorParameter(): ?string
    {
        return $this->errorParameter;
    }

}
