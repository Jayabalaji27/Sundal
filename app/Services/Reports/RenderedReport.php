<?php

namespace App\Services\Reports;

/** A finished report file, ready to download or attach to an email. */
class RenderedReport
{
    public function __construct(
        public string $content,
        public string $filename,
        public string $mime,
    ) {
    }

    public function download(): \Illuminate\Http\Response
    {
        $ascii = preg_replace('/[^A-Za-z0-9._-]+/', '_', $this->filename) ?: 'report';

        return response($this->content, 200, [
            'Content-Type' => $this->mime,
            'Content-Disposition' => sprintf(
                'attachment; filename="%s"; filename*=UTF-8\'\'%s',
                $ascii,
                rawurlencode($this->filename)
            ),
            'Content-Length' => strlen($this->content),
            'Cache-Control' => 'no-store, private',
        ]);
    }
}
