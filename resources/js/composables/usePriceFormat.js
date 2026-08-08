const config = {
  locale: 'ar-EG',
  currency: 'EGP',
  style: 'currency',
  minimumFractionDigits: 0,
  maximumFractionDigits: 0,
};

export function usePriceFormat() {
  const formatPrice = (price, placeholder = '-') => {
    if (price === null || price === undefined || price === '') return placeholder;
    try {
      return new Intl.NumberFormat(config.locale, {
        style: config.style,
        currency: config.currency,
        currencyDisplay: config.currencyDisplay,
        minimumFractionDigits: config.minimumFractionDigits,
        maximumFractionDigits: config.maximumFractionDigits,
      }).format(price);
    } catch {
      return placeholder;
    }
  };

  return { formatPrice, priceConfig: config };
}
