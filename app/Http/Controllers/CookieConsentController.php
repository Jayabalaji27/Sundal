<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;

class CookieConsentController extends Controller
{
    public function store(Request $request)
    {
        try {
            $data = $request->all();
            
            // Create CSV file path
            $fileName = 'cookie_consents.csv';
            $filePath = storage_path('app/' . $fileName);
            
            // Ensure directory exists
            if (!file_exists(dirname($filePath))) {
                mkdir(dirname($filePath), 0755, true);
            }
            
            // Check if file exists to determine if we need headers
            $fileExists = file_exists($filePath);
            
            // Open file for appending
            $file = fopen($filePath, 'a');
            
            if (!$fileExists) {
                // Write headers
                $headers = array_keys($data);
                fputcsv($file, $headers);
            }
            
            // Write data
            fputcsv($file, array_values($data));
            fclose($file);
            
            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }
    
    public function download()
    {
        $fileName = 'cookie_consents.csv';
        $filePath = storage_path('app/' . $fileName);
        
        if (!file_exists($filePath)) {
            return response()->json(['error' => 'No cookie consent data found'], 404);
        }
        
        return response()->download($filePath, 'cookie_consents_' . date('Y-m-d_H-i-s') . '.csv');
    }
}