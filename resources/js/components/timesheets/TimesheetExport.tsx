import React, { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Checkbox } from '@/components/ui/checkbox';
import { Download, FileText, FileSpreadsheet, File } from 'lucide-react';

interface ExportOptions {
    format: string;
    date_range: string;
    include_billable_only: boolean;
    include_project_details: boolean;
    include_task_details: boolean;
    include_descriptions: boolean;
    include_hourly_rates: boolean;
    group_by: string;
}

interface Props {
    onExport: (options: ExportOptions) => void;
}

export default function TimesheetExport({ onExport }: Props) {
    const [isOpen, setIsOpen] = useState(false);
    const [exportOptions, setExportOptions] = useState<ExportOptions>({
        format: 'csv',
        date_range: 'current_month',
        include_billable_only: false,
        include_project_details: true,
        include_task_details: true,
        include_descriptions: true,
        include_hourly_rates: false,
        group_by: 'date'
    });
    const [isExporting, setIsExporting] = useState(false);

    const handleExport = async () => {
        setIsExporting(true);
        try {
            await onExport(exportOptions);
            setIsOpen(false);
        } catch (error) {
            console.error('Export failed:', error);
        } finally {
            setIsExporting(false);
        }
    };

    const updateOption = (key: keyof ExportOptions, value: any) => {
        setExportOptions(prev => ({ ...prev, [key]: value }));
    };

    const getFormatIcon = (format: string) => {
        switch (format) {
            case 'pdf':
                return <File className="h-4 w-4" />;
            case 'excel':
                return <FileSpreadsheet className="h-4 w-4" />;
            default:
                return <FileText className="h-4 w-4" />;
        }
    };

    return (
        <Dialog open={isOpen} onOpenChange={setIsOpen}>
            <DialogTrigger asChild>
                <Button variant="outline">
                    <Download className="h-4 w-4 mr-2" />
                    Export
                </Button>
            </DialogTrigger>
            
            <DialogContent className="max-w-md">
                <DialogHeader>
                    <DialogTitle className="flex items-center gap-2">
                        <Download className="h-5 w-5" />
                        Export Timesheet Data
                    </DialogTitle>
                </DialogHeader>

                <div className="space-y-4">
                    {/* Format Selection */}
                    <div className="space-y-2">
                        <Label>Export Format</Label>
                        <div className="grid grid-cols-3 gap-2">
                            {[
                                { value: 'csv', label: 'CSV', icon: FileText },
                                { value: 'excel', label: 'Excel', icon: FileSpreadsheet },
                                { value: 'pdf', label: 'PDF', icon: File }
                            ].map(format => (
                                <Button
                                    key={format.value}
                                    variant={exportOptions.format === format.value ? 'default' : 'outline'}
                                    size="sm"
                                    onClick={() => updateOption('format', format.value)}
                                    className="flex flex-col gap-1 h-auto py-3"
                                >
                                    <format.icon className="h-4 w-4" />
                                    <span className="text-xs">{format.label}</span>
                                </Button>
                            ))}
                        </div>
                    </div>

                    {/* Date Range */}
                    <div className="space-y-2">
                        <Label>Date Range</Label>
                        <Select 
                            value={exportOptions.date_range} 
                            onValueChange={(value) => updateOption('date_range', value)}
                        >
                            <SelectTrigger>
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="current_week">Current Week</SelectItem>
                                <SelectItem value="current_month">Current Month</SelectItem>
                                <SelectItem value="last_month">Last Month</SelectItem>
                                <SelectItem value="current_quarter">Current Quarter</SelectItem>
                                <SelectItem value="current_year">Current Year</SelectItem>
                                <SelectItem value="all">All Time</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>

                    {/* Group By */}
                    <div className="space-y-2">
                        <Label>Group By</Label>
                        <Select 
                            value={exportOptions.group_by} 
                            onValueChange={(value) => updateOption('group_by', value)}
                        >
                            <SelectTrigger>
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="date">Date</SelectItem>
                                <SelectItem value="project">Project</SelectItem>
                                <SelectItem value="member">Member</SelectItem>
                                <SelectItem value="none">No Grouping</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>

                    {/* Include Options */}
                    <div className="space-y-3">
                        <Label>Include in Export</Label>
                        
                        <div className="space-y-2">
                            <div className="flex items-center space-x-2">
                                <Checkbox
                                    id="billable_only"
                                    checked={exportOptions.include_billable_only}
                                    onCheckedChange={(checked) => updateOption('include_billable_only', !!checked)}
                                />
                                <Label htmlFor="billable_only" className="text-sm">
                                    Billable hours only
                                </Label>
                            </div>

                            <div className="flex items-center space-x-2">
                                <Checkbox
                                    id="project_details"
                                    checked={exportOptions.include_project_details}
                                    onCheckedChange={(checked) => updateOption('include_project_details', !!checked)}
                                />
                                <Label htmlFor="project_details" className="text-sm">
                                    Project details
                                </Label>
                            </div>

                            <div className="flex items-center space-x-2">
                                <Checkbox
                                    id="task_details"
                                    checked={exportOptions.include_task_details}
                                    onCheckedChange={(checked) => updateOption('include_task_details', !!checked)}
                                />
                                <Label htmlFor="task_details" className="text-sm">
                                    Task details
                                </Label>
                            </div>

                            <div className="flex items-center space-x-2">
                                <Checkbox
                                    id="descriptions"
                                    checked={exportOptions.include_descriptions}
                                    onCheckedChange={(checked) => updateOption('include_descriptions', !!checked)}
                                />
                                <Label htmlFor="descriptions" className="text-sm">
                                    Entry descriptions
                                </Label>
                            </div>

                            <div className="flex items-center space-x-2">
                                <Checkbox
                                    id="hourly_rates"
                                    checked={exportOptions.include_hourly_rates}
                                    onCheckedChange={(checked) => updateOption('include_hourly_rates', !!checked)}
                                />
                                <Label htmlFor="hourly_rates" className="text-sm">
                                    Hourly rates & amounts
                                </Label>
                            </div>
                        </div>
                    </div>

                    {/* Export Button */}
                    <div className="flex justify-end gap-2 pt-4">
                        <Button variant="outline" onClick={() => setIsOpen(false)}>
                            Cancel
                        </Button>
                        <Button onClick={handleExport} disabled={isExporting}>
                            {getFormatIcon(exportOptions.format)}
                            <span className="ml-2">
                                {isExporting ? 'Exporting...' : `Export ${exportOptions.format.toUpperCase()}`}
                            </span>
                        </Button>
                    </div>
                </div>
            </DialogContent>
        </Dialog>
    );
}