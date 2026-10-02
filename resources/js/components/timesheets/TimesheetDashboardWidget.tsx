import React, { useState, useEffect } from 'react';
import { Link } from '@inertiajs/react';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Clock, Calendar, DollarSign, TrendingUp, ArrowRight } from 'lucide-react';

interface DashboardData {
    today_hours: number;
    week_hours: number;
    month_hours: number;
    billable_percentage: number;
    active_timer: boolean;
    recent_entries: Array<{
        project: string;
        hours: number;
        date: string;
        is_billable: boolean;
    }>;
    pending_approvals: number;
}

export default function TimesheetDashboardWidget() {
    const [data, setData] = useState<DashboardData | null>(null);
    const [loading, setLoading] = useState(true);

    useEffect(() => {
        fetchDashboardData();
    }, []);

    const fetchDashboardData = async () => {
        try {
            const response = await fetch(route('timesheet-reports.dashboard-widgets'));
            const result = await response.json();
            setData(result);
        } catch (error) {
            console.error('Failed to fetch dashboard data:', error);
        } finally {
            setLoading(false);
        }
    };

    if (loading) {
        return (
            <Card>
                <CardContent className="p-6">
                    <div className="animate-pulse space-y-4">
                        <div className="h-4 bg-gray-200 rounded w-1/4"></div>
                        <div className="space-y-2">
                            <div className="h-3 bg-gray-200 rounded"></div>
                            <div className="h-3 bg-gray-200 rounded w-5/6"></div>
                        </div>
                    </div>
                </CardContent>
            </Card>
        );
    }

    if (!data) return null;

    return (
        <Card>
            <CardHeader>
                <div className="flex justify-between items-center">
                    <CardTitle className="flex items-center gap-2">
                        <Clock className="h-5 w-5" />
                        Time Tracking
                    </CardTitle>
                    <Link href={route('timesheets.index')}>
                        <Button variant="ghost" size="sm">
                            View All <ArrowRight className="h-4 w-4 ml-1" />
                        </Button>
                    </Link>
                </div>
            </CardHeader>
            
            <CardContent className="space-y-4">
                {/* Quick Stats */}
                <div className="grid grid-cols-2 gap-4">
                    <div className="bg-blue-50 p-3 rounded-lg">
                        <div className="text-2xl font-bold text-blue-900">{(data.today_hours || 0).toFixed(1)}h</div>
                        <div className="text-sm text-blue-600">Today</div>
                    </div>
                    <div className="bg-green-50 p-3 rounded-lg">
                        <div className="text-2xl font-bold text-green-900">{(data.week_hours || 0).toFixed(1)}h</div>
                        <div className="text-sm text-green-600">This Week</div>
                    </div>
                </div>

                {/* Billable Percentage */}
                <div className="flex items-center justify-between">
                    <span className="text-sm text-muted-foreground">Billable Rate</span>
                    <div className="flex items-center gap-2">
                        <div className="w-20 bg-gray-200 rounded-full h-2">
                            <div 
                                className="bg-green-600 h-2 rounded-full" 
                                style={{ width: `${data.billable_percentage || 0}%` }}
                            ></div>
                        </div>
                        <span className="text-sm font-medium">{(data.billable_percentage || 0).toFixed(0)}%</span>
                    </div>
                </div>

                {/* Active Timer Alert */}
                {data.active_timer && (
                    <div className="bg-orange-50 border border-orange-200 p-3 rounded-lg">
                        <div className="flex items-center gap-2">
                            <Clock className="h-4 w-4 text-orange-600" />
                            <span className="text-sm text-orange-800">Timer is running</span>
                            <Badge variant="secondary">Active</Badge>
                        </div>
                    </div>
                )}

                {/* Recent Entries */}
                <div>
                    <h4 className="text-sm font-medium mb-2">Recent Entries</h4>
                    <div className="space-y-2">
                        {(data.recent_entries || []).slice(0, 3).map((entry, index) => (
                            <div key={index} className="flex justify-between items-center text-sm">
                                <div className="flex items-center gap-2">
                                    <span className="truncate">{entry.project}</span>
                                    <Badge variant={entry.is_billable ? 'default' : 'secondary'} className="text-xs">
                                        {entry.is_billable ? 'B' : 'NB'}
                                    </Badge>
                                </div>
                                <div className="flex items-center gap-2 text-muted-foreground">
                                    <span>{entry.hours}h</span>
                                    <span>{new Date(entry.date).toLocaleDateString()}</span>
                                </div>
                            </div>
                        ))}
                        
                        {(data.recent_entries || []).length === 0 && (
                            <div className="text-sm text-muted-foreground text-center py-2">
                                No recent entries
                            </div>
                        )}
                    </div>
                </div>

                {/* Pending Approvals */}
                {(data.pending_approvals || 0) > 0 && (
                    <div className="bg-yellow-50 border border-yellow-200 p-3 rounded-lg">
                        <div className="flex items-center justify-between">
                            <div className="flex items-center gap-2">
                                <Calendar className="h-4 w-4 text-yellow-600" />
                                <span className="text-sm text-yellow-800">
                                    {data.pending_approvals || 0} pending approval{(data.pending_approvals || 0) > 1 ? 's' : ''}
                                </span>
                            </div>
                            <Link href={route('timesheet-approvals.index')}>
                                <Button variant="ghost" size="sm">
                                    Review
                                </Button>
                            </Link>
                        </div>
                    </div>
                )}

                {/* Quick Actions */}
                <div className="flex gap-2">
                    <Link href={route('timesheets.daily-view')} className="flex-1">
                        <Button variant="outline" size="sm" className="w-full">
                            Daily View
                        </Button>
                    </Link>
                    <Link href={route('timesheet-reports.index')} className="flex-1">
                        <Button variant="outline" size="sm" className="w-full">
                            Reports
                        </Button>
                    </Link>
                </div>
            </CardContent>
        </Card>
    );
}