/**
 * Generate a VCF file from business data
 */
export function generateVCF(businessData: any): string {
  const { name, title, email, phone, website, location } = businessData;
  
  // Format data for VCF
  const formattedName = name || '';
  const formattedTitle = title || '';
  const formattedEmail = email || '';
  const formattedPhone = phone || '';
  const formattedWebsite = website || '';
  const formattedAddress = location || '';
  
  // Build VCF content
  let vcfContent = 'BEGIN:VCARD\n';
  vcfContent += 'VERSION:3.0\n';
  vcfContent += `FN:${formattedName}\n`;
  
  if (formattedTitle) {
    vcfContent += `TITLE:${formattedTitle}\n`;
  }
  
  if (formattedEmail) {
    vcfContent += `EMAIL;TYPE=WORK:${formattedEmail}\n`;
  }
  
  if (formattedPhone) {
    // Remove any non-numeric characters except + for international format
    const cleanPhone = formattedPhone.replace(/[^\d+]/g, '');
    vcfContent += `TEL;TYPE=WORK:${cleanPhone}\n`;
  }
  
  if (formattedWebsite) {
    vcfContent += `URL:${formattedWebsite}\n`;
  }
  
  if (formattedAddress) {
    vcfContent += `ADR;TYPE=WORK:;;${formattedAddress}\n`;
  }
  
  vcfContent += 'END:VCARD';
  
  return vcfContent;
}

/**
 * Download VCF file
 */
export function downloadVCF(businessData: any): void {
  const vcfContent = generateVCF(businessData);
  const fileName = `${businessData.name || 'contact'}.vcf`;
  
  // Create a blob with the VCF content
  const blob = new Blob([vcfContent], { type: 'text/vcard' });
  const url = URL.createObjectURL(blob);
  
  // Create a temporary link and trigger download
  const link = document.createElement('a');
  link.href = url;
  link.download = fileName;
  document.body.appendChild(link);
  link.click();
  
  // Clean up
  setTimeout(() => {
    document.body.removeChild(link);
    URL.revokeObjectURL(url);
  }, 100);
}