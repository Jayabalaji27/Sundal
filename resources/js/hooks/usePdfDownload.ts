import { useState } from 'react';
import html2canvas from 'html2canvas';
import jsPDF from 'jspdf';

export const usePdfDownload = () => {
    const [isGeneratingPDF, setIsGeneratingPDF] = useState(false);

    const downloadPDF = async (element: HTMLElement, filename: string) => {
        if (!element) return;

        setIsGeneratingPDF(true);

        try {
            // 1. Create a hidden iframe
            const iframe = document.createElement('iframe');
            iframe.style.position = 'absolute';
            iframe.style.width = '1000px'; // Give it a fixed width for perfect standard A4 rendering
            iframe.style.height = '1414px'; // A4 aspect ratio height
            iframe.style.left = '-9999px';
            iframe.style.top = '-9999px';
            iframe.style.border = 'none';
            document.body.appendChild(iframe);

            const iframeDoc = iframe.contentDocument || iframe.contentWindow!.document;
            
            // 2. Clone the target element's HTML
            const clone = element.cloneNode(true) as HTMLElement;
            
            // Strip out any oklch styles from inline styles of the clone
            const oklchElements = clone.querySelectorAll('[style*="oklch"]');
            oklchElements.forEach((el) => {
                const style = el.getAttribute('style') || '';
                const safeStyle = style.replace(/oklch\([^\)]+\)/g, 'rgb(0, 0, 0)');
                el.setAttribute('style', safeStyle);
            });

            // Write HTML content into the iframe
            iframeDoc.open();
            iframeDoc.write(`
                <!DOCTYPE html>
                <html>
                <head>
                    <meta charset="utf-8">
                    <title>Invoice Preview</title>
                    <style>
                        body {
                            margin: 0;
                            padding: 0;
                            background: #ffffff;
                            font-family: system-ui, -apple-system, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
                            -webkit-print-color-adjust: exact;
                        }
                        button, svg, .fixed {
                            display: none !important;
                        }
                    </style>
                </head>
                <body>
                    <div id="print-root" style="width: 900px; margin: 0 auto; background: #ffffff;">
                        ${clone.outerHTML}
                    </div>
                </body>
                </html>
            `);
            iframeDoc.close();

            // 3. Wait for all resources (images, fonts, QR codes) inside the iframe to load
            await new Promise<void>((resolve) => {
                const images = Array.from(iframeDoc.images);
                let loadedCount = 0;
                const totalImages = images.length;

                if (totalImages === 0) {
                    setTimeout(resolve, 300);
                    return;
                }

                const checkResolve = () => {
                    loadedCount++;
                    if (loadedCount >= totalImages) {
                        setTimeout(resolve, 300);
                    }
                };

                images.forEach((img) => {
                    if (img.complete) {
                        checkResolve();
                    } else {
                        img.addEventListener('load', checkResolve);
                        img.addEventListener('error', checkResolve);
                    }
                });
            });

            // 4. Capture the element from inside the iframe context
            const targetInIframe = iframeDoc.getElementById('print-root');
            if (!targetInIframe) {
                throw new Error("Target element not found in iframe");
            }

            // Run html2canvas inside the iframe window context
            const canvas = await html2canvas(targetInIframe, {
                scale: 2,
                useCORS: true,
                allowTaint: true,
                backgroundColor: '#ffffff',
                logging: false,
                windowWidth: 1000,
                windowHeight: 1414
            });

            // 5. Remove the iframe from the DOM
            document.body.removeChild(iframe);

            // 6. Generate and save the PDF (using robust JPEG formatting to bypass fragile PNG parsing crashes)
            const imgData = canvas.toDataURL('image/png', 1.0);
            const pdf = new jsPDF({
                orientation: 'portrait',
                unit: 'mm',
                format: 'a4'
            });

            const pageWidth = pdf.internal.pageSize.getWidth();
            const pageHeight = pdf.internal.pageSize.getHeight();
            const imgWidth = pageWidth - 10;
            const imgHeight = (canvas.height * imgWidth) / canvas.width;
            let heightLeft = imgHeight;
            let position = 0;

            pdf.addImage(imgData, 'PNG', 5, position + 5, imgWidth, imgHeight);
            heightLeft -= pageHeight - 10;

            while (heightLeft > 0) {
                position = heightLeft - imgHeight;
                pdf.addPage();
                pdf.addImage(imgData, 'PNG', 5, position + 5, imgWidth, imgHeight);
                heightLeft -= pageHeight - 10;
            }

            pdf.save(filename);
        } catch (error) {
            console.error('Error generating PDF:', error);
        } finally {
            setIsGeneratingPDF(false);
        }
    };

    return { downloadPDF, isGeneratingPDF };
};
