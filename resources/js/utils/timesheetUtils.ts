/**
 * Utility functions for timesheet operations
 */

export interface TimesheetData {
    total_hours: number;
    billable_hours: number;
    end_date: string;
    status: string;
}

export interface TimesheetLabel {
    label: string;
    className: string;
}

export interface HoursDisplay {
    total: string;
    billable: string;
    percentage: number;
    match: boolean;
}

/**
 * Format hours display with total and billable hours
 */
export function formatHoursDisplay(totalHours: number | string, billableHours: number | string): HoursDisplay {
    const total = parseFloat(String(totalHours || 0));
    const billable = parseFloat(String(billableHours || 0));
    const percentage = total > 0 ? Math.round((billable / total) * 100) : 0;
    const match = total > 0 && total === billable;

    return {
        total: `${total.toFixed(1)}h`,
        billable: `${billable.toFixed(1)}h`,
        percentage,
        match
    };
}

/**
 * Check if a timesheet is overdue
 */
export function isTimesheetOverdue(endDate: string, status: string): boolean {
    if (status === 'approved' || status === 'submitted') {
        return false;
    }
    
    const end = new Date(endDate);
    const now = new Date();
    
    // Set time to end of day for end date and start of day for current date for fair comparison
    end.setHours(23, 59, 59, 999);
    now.setHours(0, 0, 0, 0);
    
    return end < now;
}

/**
 * Get the number of days a timesheet is overdue
 */
export function getDaysOverdue(endDate: string): number {
    const end = new Date(endDate);
    const now = new Date();
    
    // Set time to end of day for end date and start of day for current date
    end.setHours(23, 59, 59, 999);
    now.setHours(0, 0, 0, 0);
    
    if (end >= now) {
        return 0;
    }
    
    const diffTime = now.getTime() - end.getTime();
    const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
    
    return diffDays;
}

/**
 * Get a label for timesheet status with appropriate styling
 */
export function getTimesheetLabel(timesheet: TimesheetData): TimesheetLabel | null {
    const { total_hours, billable_hours, end_date, status } = timesheet;
    
    // Check if overdue
    if (isTimesheetOverdue(end_date, status)) {
        const daysOverdue = getDaysOverdue(end_date);
        return {
            label: `${daysOverdue} day${daysOverdue !== 1 ? 's' : ''} overdue`,
            className: 'bg-red-100 text-red-800 border-red-200'
        };
    }
    
    // Check hours status
    if (total_hours > 0) {
        if (total_hours === billable_hours) {
            return {
                label: 'All hours billable',
                className: 'bg-green-100 text-green-800 border-green-200'
            };
        } else if (billable_hours > 0) {
            const percentage = Math.round((billable_hours / total_hours) * 100);
            return {
                label: `${percentage}% billable`,
                className: 'bg-yellow-100 text-yellow-800 border-yellow-200'
            };
        } else {
            return {
                label: 'No billable hours',
                className: 'bg-gray-100 text-gray-800 border-gray-200'
            };
        }
    }
    
    // Check status
    switch (status) {
        case 'approved':
            return {
                label: 'Approved',
                className: 'bg-green-100 text-green-800 border-green-200'
            };
        case 'submitted':
            return {
                label: 'Pending approval',
                className: 'bg-blue-100 text-blue-800 border-blue-200'
            };
        case 'rejected':
            return {
                label: 'Rejected',
                className: 'bg-red-100 text-red-800 border-red-200'
            };
        case 'draft':
            return {
                label: 'Draft',
                className: 'bg-gray-100 text-gray-800 border-gray-200'
            };
        default:
            return null;
    }
}