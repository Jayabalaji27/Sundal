import React, { useState, type ReactNode } from 'react';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogFooter } from './ui/dialog';
import { Button } from './ui/button';
import { Input } from './ui/input';
import { Label } from './ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from './ui/select';
import { Upload, FileText, Download, AlertCircle, CheckCircle2, TriangleAlert } from 'lucide-react';
import { toast } from './custom-toast';
import { router } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';

interface ImportModalProps {
  isOpen: boolean;
  onClose: () => void;
  type: string;
  title: string;
  /** Sent with the final import request (e.g. the project a task import goes into). */
  extraData?: Record<string, unknown>;
  /** Replaces the generic "Import Notes" text on the first step. */
  notes?: ReactNode;
  /** Fields that must be matched to a column before importing. */
  requiredFields?: string[];
}

interface ImportResult {
  message: string;
  errors: string[];
  warnings: string[];
}

const SKIP = '__skip__';
const fieldLabel = (field: string) => field.replace(/_/g, ' ').replace(/\b\w/g, (l) => l.toUpperCase());

export const ImportModal: React.FC<ImportModalProps> = ({
  isOpen,
  onClose,
  type,
  title,
  extraData,
  notes,
  requiredFields = [],
}) => {
  const { t } = useTranslation();
  const [file, setFile] = useState<File | null>(null);
  const [isLoading, setIsLoading] = useState(false);
  const [showMapping, setShowMapping] = useState(false);
  const [headers, setHeaders] = useState<string[]>([]);
  const [fields, setFields] = useState<string[]>([]);
  const [preview, setPreview] = useState<string[][]>([]);
  const [mapping, setMapping] = useState<Record<string, number>>({});
  const [result, setResult] = useState<ImportResult | null>(null);
  const missingRequired = requiredFields.filter((field) => mapping[field] === undefined);

  const handleImport = async () => {
    if (!file) {
      toast.error(t('Please select a file to import'));
      return;
    }

    setIsLoading(true);

    const formData = new FormData();
    formData.append('file', file);
    formData.append('table', type);
    formData.append('_token', document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '');

    try {
      const response = await fetch(route('csv.import'), {
        method: 'POST',
        headers: { 'Accept': 'application/json' },
        body: formData,
      });

      if (!response.ok) {
        // The server may return a non-JSON error page (e.g. a CSRF/419 or
        // validation failure) instead of the expected JSON body — surface
        // whatever it actually sent rather than swallowing it as a generic
        // failure toast that gives no clue what went wrong.
        let message = t('Upload failed');
        try {
          const errJson = await response.clone().json();
          message = errJson.error || errJson.message || message;
        } catch {
          const text = await response.text();
          if (text) message = text.replace(/<[^>]*>/g, '').trim().slice(0, 200) || message;
        }
        toast.error(message);
        return;
      }

      const result = await response.json();

      if (result.error !== '') {
        toast.error(result.error);
      } else {
        // Get the modal data
        const modalResponse = await fetch(route('csv.import.modal', { table: type, status: true }));
        const modalData = await modalResponse.json();
        
        // Parse the fields JSON
        const fieldsArray = JSON.parse(modalData.fields);
        
        setHeaders(modalData.headers);
        setFields(fieldsArray);
        // Columns the server recognised by name start out matched.
        setMapping(Object.fromEntries(
          Object.entries(modalData.suggested || {}).map(([field, index]) => [field, Number(index)])
        ));

        // Convert HTML table to preview data
        const parser = new DOMParser();
        const doc = parser.parseFromString(`<table>${result.output}</table>`, 'text/html');
        const rows = Array.from(doc.querySelectorAll('tr'));
        const previewData = rows.map(row => 
          Array.from(row.querySelectorAll('td')).map(cell => cell.textContent || '')
        );
        
        setPreview(previewData);
        setShowMapping(true);
      }
    } catch (error) {
      toast.error(t('Upload failed'));
    } finally {
      setIsLoading(false);
    }
  };

  /** Each column fills at most one field and each field comes from one column. */
  const handleFieldMapping = (field: string, columnIndex: number) => {
    setMapping(prev => {
      const next = Object.fromEntries(Object.entries(prev).filter(([, index]) => index !== columnIndex));
      if (field !== SKIP) next[field] = columnIndex;
      return next;
    });
  };

  const fieldForColumn = (columnIndex: number) =>
    Object.keys(mapping).find(field => mapping[field] === columnIndex);

  const getAvailableFields = (currentColumnIndex: number) => {
    // Get all currently mapped fields except for the current column
    const mappedFields = Object.keys(mapping).filter(field =>
      mapping[field] !== currentColumnIndex
    );

    // Return fields that are not already mapped
    return fields.filter(field => !mappedFields.includes(field));
  };

  const canProceed = () => {
    return Object.keys(mapping).length > 0 && missingRequired.length === 0;
  };

  const handleFinalImport = async () => {
    if (!canProceed()) return;

    setIsLoading(true);

    try {
      const response = await fetch(route(`${type}.import`), {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
          'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
        },
        body: JSON.stringify({
          ...extraData,
          data: mapping
        }),
      });

      if (!response.ok) {
        let message = t('Import failed');
        try {
          const errJson = await response.clone().json();
          message = errJson.error || errJson.message || message;
        } catch {
          const text = await response.text();
          if (text) message = text.replace(/<[^>]*>/g, '').trim().slice(0, 200) || message;
        }
        toast.error(message);
        return;
      }

      const data = await response.json();
      const errors: string[] = data.errors || [];
      const warnings: string[] = data.warnings || [];

      if (errors.length > 0 || warnings.length > 0) {
        // Some rows need attention: show which, instead of a toast that vanishes.
        setResult({ message: data.message || '', errors, warnings });
      } else if (data.success) {
        toast.success(data.message);
        resetState();
        onClose();
        router.reload();
      } else {
        toast.error(data.error || data.message || t('Import failed'));
      }
    } catch (error) {
      toast.error(t('Import failed'));
    } finally {
      setIsLoading(false);
    }
  };

  const resetState = () => {
    setFile(null);
    setShowMapping(false);
    setHeaders([]);
    setFields([]);
    setPreview([]);
    setMapping({});
    setResult(null);
  };

  const handleClose = () => {
    // After an import with row problems, closing still shows what was imported.
    const imported = result !== null;
    resetState();
    onClose();
    if (imported) router.reload();
  };

  const handleDownloadTemplate = async () => {
    try {
      const templateUrl = route(`${type}.template`);
      
      
      const response = await fetch(templateUrl, {
        method: 'GET',
        headers: {
          'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
          'Accept': 'application/json',
        },
      });
      
      if (response.ok) {
        const blob = await response.blob();
        const url = window.URL.createObjectURL(blob); 
        const a = document.createElement('a');
        a.href = url;
          a.download = `sample_${type}.csv`;
        document.body.appendChild(a);
        a.click();
        window.URL.revokeObjectURL(url);
        document.body.removeChild(a);
        toast.success(t('Template downloaded successfully'));
      } else {
        const errorText = await response.text();
        console.error('Template download failed:', response.status, errorText);
        toast.error(t('Template download failed'));
      }
    } catch (error) {
      console.error('Template download error:', error);
      toast.error(t('Template download failed'));
    }
  };

  return (
    <Dialog open={isOpen} onOpenChange={handleClose}>
      <DialogContent className={result ? "sm:max-w-2xl max-h-[85vh] overflow-hidden flex flex-col" : showMapping ? "max-w-6xl max-h-[90vh] overflow-hidden flex flex-col" : "sm:max-w-lg"}>
        <DialogHeader>
          <DialogTitle>{t('Import')} {t(title)}</DialogTitle>
        </DialogHeader>

        {!showMapping ? (
          <>
            <div className="space-y-4">
              <div className="flex items-center justify-between p-3 border border-gray-200 rounded-md">
                <p className="text-sm text-gray-700">{t('Download sample template for required format')}</p>
                <Button
                  type="button"
                  variant="ghost"
                  size="sm"
                  onClick={handleDownloadTemplate}
                  className="ml-3 text-blue-600 hover:text-blue-800"
                >
                  <Download className="h-4 w-4" />
                </Button>
              </div>

              <div className="space-y-2">
                <Label htmlFor="file">{t('Select File')} <span className="text-red-500">*</span></Label>
                <Input
                  id="file"
                  type="file"
                  accept=".xlsx,.xls,.csv"
                  onChange={(e) => setFile(e.target.files?.[0] || null)}
                  disabled={isLoading}
                  className="w-full"
                />
              </div>

              <div className="bg-blue-50 border border-blue-200 rounded-md p-3">
                <h4 className="text-sm font-medium text-blue-800 mb-1">{t('Import Notes:')}</h4>
                {notes ?? (
                  <p className="text-xs text-blue-700">{t('Import')} {type} {t('data from Excel (.xlsx, .xls) or CSV (.csv) file')}</p>
                )}
              </div>
            </div>

            <DialogFooter>
              <Button type="button" variant="outline" onClick={handleClose} disabled={isLoading}>
                {t('Cancel')}
              </Button>
              <Button
                type="button"
                onClick={handleImport}
              >
                {isLoading ? t('Processing...') : t('Import')}
              </Button>
            </DialogFooter>
          </>
        ) : result ? (
          <div className="flex flex-col gap-4 min-h-0 flex-1 overflow-hidden">
            <div className="flex items-center gap-2 text-sm bg-muted p-3 rounded shrink-0" role="status">
              <CheckCircle2 className="w-4 h-4 text-primary shrink-0" />
              <span className="font-medium">{result.message}</span>
            </div>
            <div className="overflow-auto min-h-0 flex-1 space-y-4">
              {result.errors.length > 0 && (
                <div>
                  <h4 className="text-sm font-medium text-destructive mb-1 flex items-center gap-1.5">
                    <AlertCircle className="w-4 h-4" />
                    {t('Not imported')} ({result.errors.length})
                  </h4>
                  <ul className="text-sm space-y-1 list-disc pl-5">
                    {result.errors.map((error, index) => <li key={index}>{error}</li>)}
                  </ul>
                </div>
              )}
              {result.warnings.length > 0 && (
                <div>
                  <h4 className="text-sm font-medium text-amber-700 mb-1 flex items-center gap-1.5">
                    <TriangleAlert className="w-4 h-4" />
                    {t('Imported with changes')} ({result.warnings.length})
                  </h4>
                  <ul className="text-sm space-y-1 list-disc pl-5 text-muted-foreground">
                    {result.warnings.map((warning, index) => <li key={index}>{warning}</li>)}
                  </ul>
                </div>
              )}
            </div>
            <div className="flex justify-end shrink-0">
              <Button onClick={handleClose}>{t('Done')}</Button>
            </div>
          </div>
        ) : (
          <div className="flex flex-col gap-4 min-h-0 flex-1 overflow-hidden">
            <div className="flex items-center gap-2 text-sm text-amber-600 bg-amber-50 p-3 rounded shrink-0">
              <AlertCircle className="w-4 h-4 shrink-0" />
              {t('Match each column to a field. Columns we recognised are already matched; choose "Don\'t import" for the rest.')}
            </div>

            {preview.length > 0 && (
              <div className="flex flex-col min-h-0 flex-1">
                <h4 className="font-medium mb-2 shrink-0">{t('Map CSV Columns to Database Fields')}</h4>
                <div className="border rounded overflow-auto flex-1">
                  <table className="min-w-full text-sm">
                    <thead className="bg-gray-50 sticky top-0">
                      <tr>
                        {headers.map((header, index) => (
                          <th key={index} className="px-3 py-2 text-center relative">
                            <div className="mb-2 font-medium">{header}</div>
                            <div className="relative z-50">
                              <Select value={fieldForColumn(index) ?? SKIP} onValueChange={(value) => handleFieldMapping(value, index)}>
                                <SelectTrigger className="w-full" aria-label={t('Field for column {{column}}', { column: header })}>
                                  <SelectValue placeholder={t('Select field')} />
                                </SelectTrigger>
                                <SelectContent className="z-[100] bg-white border shadow-lg">
                                  <SelectItem value={SKIP}>{t("Don't import")}</SelectItem>
                                  {getAvailableFields(index).map((field) => (
                                    <SelectItem key={field} value={field}>
                                      {fieldLabel(field)}{requiredFields.includes(field) ? ' *' : ''}
                                    </SelectItem>
                                  ))}
                                </SelectContent>
                              </Select>
                            </div>
                          </th>
                        ))}
                      </tr>
                    </thead>
                    <tbody>
                      {preview.map((row, index) => (
                        <tr key={index} className="border-t">
                          {row.map((cell, cellIndex) => (
                            <td key={cellIndex} className="px-3 py-2 text-center">
                              {cell}
                            </td>
                          ))}
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>
              </div>
            )}

            <div className="flex flex-wrap items-center justify-end gap-2 shrink-0">
              {missingRequired.length > 0 && (
                <p className="mr-auto text-sm text-destructive">
                  {t('Choose a column for: {{fields}}', { fields: missingRequired.map(fieldLabel).join(', ') })}
                </p>
              )}
              <Button variant="outline" onClick={() => setShowMapping(false)}>
                {t('Back')}
              </Button>
              <Button onClick={handleFinalImport} disabled={isLoading || !canProceed()}>
                {isLoading ? t('Importing...') : t('Import Data')}
              </Button>
            </div>
          </div>
        )}
      </DialogContent>
    </Dialog>
  );
};