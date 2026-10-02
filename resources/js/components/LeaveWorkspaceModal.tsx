import React from 'react';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { LogOut, AlertTriangle } from 'lucide-react';
import { useTranslation } from 'react-i18next';

interface LeaveWorkspaceModalProps {
    isOpen: boolean;
    onClose: () => void;
    onConfirm: () => void;
    workspaceName: string;
    isProcessing?: boolean;
}

export const LeaveWorkspaceModal: React.FC<LeaveWorkspaceModalProps> = ({
    isOpen,
    onClose,
    onConfirm,
    workspaceName,
    isProcessing = false
}) => {
    const { t } = useTranslation();

    return (
        <Dialog open={isOpen} onOpenChange={onClose}>
            <DialogContent className="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle className="flex items-center gap-2">
                        <LogOut className="h-5 w-5 text-orange-500" />
                        {t('Leave Workspace')}
                    </DialogTitle>
                    <DialogDescription>
                        {t('Are you sure you want to leave')} <strong>{workspaceName}</strong>?
                    </DialogDescription>
                </DialogHeader>
                
                <div className="flex items-start gap-3 p-4 bg-orange-50 border border-orange-200 rounded-lg">
                    <AlertTriangle className="h-5 w-5 text-orange-500 flex-shrink-0 mt-0.5" />
                    <div className="text-sm text-orange-800">
                        <p className="font-medium mb-1">{t('Warning')}</p>
                        <p>{t('You will lose access to all projects, tasks, and data in this workspace. This action cannot be undone.')}</p>
                    </div>
                </div>

                <DialogFooter>
                    <Button variant="outline" onClick={onClose} disabled={isProcessing}>
                        {t('Cancel')}
                    </Button>
                    <Button 
                        variant="destructive" 
                        onClick={onConfirm}
                        disabled={isProcessing}
                        className="bg-orange-600 hover:bg-orange-700"
                    >
                        <LogOut className="h-4 w-4 mr-2" />
                        {isProcessing ? t('Leaving...') : t('Leave Workspace')}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
};