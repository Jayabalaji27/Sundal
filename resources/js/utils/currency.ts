export const formatCurrency = (amount: string | number) => {
  if (typeof window !== 'undefined' && window.appSettings?.formatCurrency) {
    const numericAmount = typeof amount === 'number' ? amount : parseFloat(amount) || 0;
    return window.appSettings.formatCurrency(numericAmount, { showSymbol: true });
  }
  return amount || 0;
};

export const useCurrencyFormatter = () => {
  return formatCurrency;
};