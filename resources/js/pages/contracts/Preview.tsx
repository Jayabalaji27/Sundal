import { usePage, Head } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { useTranslation } from 'react-i18next';
import { Download } from 'lucide-react';
import { formatCurrency } from '@/utils/currency';
import { useRef } from 'react';
import { usePdfDownload } from '@/hooks/usePdfDownload';
import { useBrand } from '@/contexts/BrandContext';
import { THEME_COLORS } from '@/hooks/use-appearance';

interface Contract {
    id: number;
    contract_id: string;
    subject: string;
    description: string;
    contract_value: number;
    currency: string;
    start_date: string;
    end_date: string;
    status: string;
    terms_conditions?: string;
    notes?: string;
    company_signature?: string;
    signed_at?: string;
    sent_at?: string;
    created_at: string;
    contract_type: {
        id: number;
        name: string;
        color: string;
    };
    client: {
        id: number;
        name: string;
        email: string;
    };
    creator: {
        id: number;
        name: string;
    };
}

const statusOptions = [
    { value: 'pending', label: 'Pending', color: '#ffc107' },
    { value: 'sent', label: 'Sent', color: '#007bff' },
    { value: 'accept', label: 'Accept', color: '#28a745' },
    { value: 'decline', label: 'Decline', color: '#dc3545' },
    { value: 'expired', label: 'Expired', color: '#fd7e14' },
];

export default function ContractPreview() {
    const { t } = useTranslation();
    const { contract } = usePage().props as { contract: Contract };
    const contractRef = useRef<HTMLDivElement>(null);
    const { downloadPDF, isGeneratingPDF } = usePdfDownload();
    const { themeColor, customColor } = useBrand();
    const primaryColor = themeColor === 'custom' ? customColor : THEME_COLORS[themeColor as keyof typeof THEME_COLORS];

    const hexToRgb = (hex: string) => {
        const h = hex.replace('#', '');
        return {
            r: parseInt(h.substring(0, 2), 16),
            g: parseInt(h.substring(2, 4), 16),
            b: parseInt(h.substring(4, 6), 16),
        };
    };

    const getStatusBadge = (status: string) => {
        const statusOption = statusOptions.find(s => s.value === status);
        const label = statusOption?.label || status;
        const color = statusOption?.color || '#6b7280';
        const { r, g, b } = hexToRgb(color);
        return (
            <span
                style={{
                    display: 'inline-block',
                    padding: '3px 10px',
                    borderRadius: '5px',
                    fontSize: '12px',
                    fontWeight: 600,
                    lineHeight: '1.4',
                    backgroundColor: `rgba(${r}, ${g}, ${b}, 0.12)`,
                    color: color,
                    border: `1px solid rgba(${r}, ${g}, ${b}, 0.35)`,
                }}
            >
                {label}
            </span>
        );
    };

    const handleDownloadPDF = () => {
        if (!contractRef.current) return;
        const filename = `${contract.subject}_${contract.contract_id}.pdf`;
        downloadPDF(contractRef.current, filename);
    };

    const durationDays = Math.ceil((new Date(contract.end_date).getTime() - new Date(contract.start_date).getTime()) / (1000 * 60 * 60 * 24));

    return (
        <div style={{ backgroundColor: '#f9fafb', minHeight: '100vh', padding: '32px' }}>
             <Head title={`${contract.subject} - ${(usePage().props as any).globalSettings?.titleText || 'Taskly'}`} />
            {/* Fixed Download Button */}
            <div style={{ position: 'fixed', top: '16px', right: '16px', zIndex: 10 }}>
                <Button
                    onClick={handleDownloadPDF}
                    disabled={isGeneratingPDF}
                >
                    <Download style={{ height: '16px', width: '16px', marginRight: '8px' }} />
                    {isGeneratingPDF ? 'Generating PDF...' : t('Download PDF')}
                </Button>
            </div>

            {/* Contract Preview Container */}
            <div ref={contractRef} style={{ maxWidth: '900px', margin: '0 auto', backgroundColor: 'white', padding: '48px', boxShadow: '0 1px 3px rgba(0,0,0,0.1)', borderRadius: '8px' }}>
                
                {/* Header with Title */}
                <div style={{ textAlign: 'center', borderBottom: `2px solid ${primaryColor}`, paddingBottom: '24px', marginBottom: '32px' }}>
                    <div style={{ fontSize: '32px', fontWeight: 'bold', color: '#1f2937', marginBottom: '8px' }}>
                        {contract.subject}
                    </div>
                    <div style={{ fontSize: '16px', color: '#6b7280' }}>
                        Contract ID: {contract.contract_id}
                    </div>
                </div>

                {/* Contract Information Section */}
                <div style={{ marginBottom: '32px' }}>
                    <div style={{ fontSize: '18px', fontWeight: 'bold', color: '#1f2937', borderBottom: '1px solid #e5e7eb', paddingBottom: '8px', marginBottom: '16px' }}>
                        Contract Information
                    </div>
                    <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '24px' }}>
                        <div>
                            <div style={{ fontWeight: '600', color: '#374151', marginBottom: '4px', fontSize: '14px' }}>Status:</div>
                            <div>{getStatusBadge(contract.status)}</div>
                        </div>
                        <div>
                            <div style={{ fontWeight: '600', color: '#374151', marginBottom: '4px', fontSize: '14px' }}>Type:</div>
                            <div style={{ fontSize: '16px', color: '#374151' }}>{contract.contract_type?.name || 'N/A'}</div>
                        </div>
                        <div>
                            <div style={{ fontWeight: '600', color: '#374151', marginBottom: '4px', fontSize: '14px' }}>Contract Value:</div>
                            <div style={{ fontSize: '16px', color: '#374151' }}>
                                {formatCurrency(contract.contract_value || 0)}
                            </div>
                        </div>
                        <div>
                            <div style={{ fontWeight: '600', color: '#374151', marginBottom: '4px', fontSize: '14px' }}>Duration:</div>
                            <div style={{ fontSize: '16px', color: '#374151' }}>
                                {durationDays} days
                            </div>
                        </div>
                        <div>
                            <div style={{ fontWeight: '600', color: '#374151', marginBottom: '4px', fontSize: '14px' }}>Start Date:</div>
                            <div style={{ fontSize: '16px', color: '#374151' }}>
                                {new Date(contract.start_date).toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' })}
                            </div>
                        </div>
                        <div>
                            <div style={{ fontWeight: '600', color: '#374151', marginBottom: '4px', fontSize: '14px' }}>End Date:</div>
                            <div style={{ fontSize: '16px', color: '#374151' }}>
                                {new Date(contract.end_date).toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' })}
                            </div>
                        </div>
                    </div>
                </div>

                {/* Parties Section */}
                <div style={{ marginBottom: '32px' }}>
                    <div style={{ fontSize: '18px', fontWeight: 'bold', color: '#1f2937', borderBottom: '1px solid #e5e7eb', paddingBottom: '8px', marginBottom: '16px' }}>
                        Parties
                    </div>
                    <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '24px' }}>
                        <div>
                            <div style={{ fontWeight: '600', color: '#374151', marginBottom: '8px', fontSize: '14px' }}>Client:</div>
                            <div style={{ fontSize: '16px', color: '#374151' }}>
                                <div style={{ fontWeight: '600' }}>{contract.client?.name || 'N/A'}</div>
                                <div style={{ fontSize: '14px', color: '#6b7280' }}>{contract.client?.email || ''}</div>
                            </div>
                        </div>
                        <div>
                            <div style={{ fontWeight: '600', color: '#374151', marginBottom: '8px', fontSize: '14px' }}>Created By:</div>
                            <div style={{ fontSize: '16px', color: '#374151' }}>
                                <div style={{ fontWeight: '600' }}>{contract.creator?.name || 'N/A'}</div>
                                <div style={{ fontSize: '14px', color: '#6b7280' }}>{contract.creator?.email || ''}</div>
                            </div>
                        </div>
                    </div>

                    {contract.sent_at && (
                        <div style={{ marginTop: '16px' }}>
                            <div style={{ fontWeight: '600', color: '#374151', marginBottom: '4px', fontSize: '14px' }}>Sent At:</div>
                            <div style={{ fontSize: '16px', color: '#374151' }}>
                                {new Date(contract.sent_at).toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' })}
                            </div>
                        </div>
                    )}
                </div>

                {/* Description Section */}
                {contract.description && (
                    <div style={{ marginBottom: '32px' }}>
                        <div style={{ fontSize: '18px', fontWeight: 'bold', color: '#1f2937', borderBottom: '1px solid #e5e7eb', paddingBottom: '8px', marginBottom: '16px' }}>
                            Description
                        </div>
                        <div style={{ backgroundColor: primaryColor + '15', padding: '16px', borderLeft: `4px solid ${primaryColor}`, borderRadius: '4px' }}>
                            <p style={{ color: '#374151', lineHeight: '1.6', fontSize: '16px', margin: 0 }}>
                                {contract.description}
                            </p>
                        </div>
                    </div>
                )}

                {/* Terms & Conditions Section */}
                {contract.terms_conditions && (
                    <div style={{ marginBottom: '32px' }}>
                        <div style={{ fontSize: '18px', fontWeight: 'bold', color: '#1f2937', borderBottom: '1px solid #e5e7eb', paddingBottom: '8px', marginBottom: '16px' }}>
                            Terms & Conditions
                        </div>
                        <div style={{ border: '1px solid #e5e7eb', padding: '16px', borderRadius: '4px' }}>
                            <p style={{ color: '#374151', lineHeight: '1.6', fontSize: '16px', whiteSpace: 'pre-wrap', margin: 0 }}>
                                {contract.terms_conditions}
                            </p>
                        </div>
                    </div>
                )}

                {/* Signatures Section */}
                <div style={{ marginTop: '40px', padding: '24px', border: '1px solid #e5e7eb', borderRadius: '4px' }}>
                    <div style={{ fontSize: '18px', fontWeight: 'bold', color: '#1f2937', borderBottom: '1px solid #e5e7eb', paddingBottom: '8px', marginBottom: '24px' }}>
                        Signatures
                    </div>
                    
                    <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '32px' }}>
                        {/* Company Signature */}
                        <div>
                            <p style={{ fontWeight: '600', fontSize: '14px', marginBottom: '12px', color: '#374151' }}>Company Signature:</p>
                            {contract.company_signature ? (
                                <>
                                    <div style={{ border: '1px solid #e5e7eb', padding: '8px', display: 'inline-block', borderRadius: '4px' }}>
                                        <img 
                                            src={contract.company_signature} 
                                            alt="Company Signature" 
                                            style={{ maxHeight: '60px', maxWidth: '160px' }}
                                        />
                                    </div>
                                    <p style={{ marginTop: '12px', fontSize: '14px', color: '#374151' }}>
                                        {contract.creator?.name || 'Representative Name'}
                                    </p>
                                </>
                            ) : (
                                <>
                                    <div style={{ borderBottom: '1px solid #1f2937', width: '100%', height: '40px', marginBottom: '8px' }}></div>
                                    <p style={{ fontSize: '14px', color: '#374151' }}>
                                        {contract.creator?.name || 'Representative Name'}
                                    </p>
                                </>
                            )}
                        </div>

                        {/* Client Signature */}
                        <div>
                            <p style={{ fontWeight: '600', fontSize: '14px', marginBottom: '12px', color: '#374151' }}>Client Signature:</p>
                            {contract.client_signature ? (
                                <>
                                    <div style={{ border: '1px solid #e5e7eb', padding: '8px', display: 'inline-block', borderRadius: '4px' }}>
                                        <img 
                                            src={contract.client_signature} 
                                            alt="Client Signature" 
                                            style={{ maxHeight: '60px', maxWidth: '160px' }}
                                        />
                                    </div>
                                    <p style={{ marginTop: '12px', fontSize: '14px', color: '#374151' }}>
                                        {contract.client?.name || 'Client Name'}
                                    </p>
                                </>
                            ) : (
                                <>
                                    <div style={{ borderBottom: '1px solid #1f2937', width: '100%', height: '40px', marginBottom: '8px' }}></div>
                                    <p style={{ fontSize: '14px', color: '#374151' }}>
                                        {contract.client?.name || 'Client Name'}
                                    </p>
                                </>
                            )}
                        </div>
                    </div>
                    
                    {contract.status === 'signed' && contract.signed_at && (
                        <div style={{ marginTop: '20px', padding: '12px', backgroundColor: '#dcfce7', border: '1px solid #86efac', borderRadius: '4px' }}>
                            <strong style={{ fontSize: '14px', color: '#166534' }}>Contract Status:</strong> 
                            <span style={{ fontSize: '14px', color: '#166534', marginLeft: '8px' }}>
                                Signed on {new Date(contract.signed_at).toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric', hour: 'numeric', minute: '2-digit' })}
                            </span>
                        </div>
                    )}
                </div>
            </div>
        </div>
    );
}
