<?php

namespace App\Services\Ai\Attachments;

use App\Models\AiAttachment;
use App\Models\User;
use App\Services\Ai\Tools\ToolInputException;

/**
 * Reads a scanned PDF (no text layer). Filled in by Phase 4; until then it
 * explains why the file cannot be read.
 */
class ScannedPdfReader
{
    public function read(AiAttachment $file, User $user): AiAttachment
    {
        throw new ToolInputException(__('":file" is a scanned PDF with no text, so it cannot be read yet. Ask the user for a text PDF or a Word file.', ['file' => $file->original_name]));
    }
}
