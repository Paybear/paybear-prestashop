$(function () {
    var $container = $('#paybear');
    window.paybear = new Paybear({
        // button: '#paybear-all',
        fiatValue: $container.data('fiat-value'), // 19.95,
        currencies: $container.data('currencies'),// currencies.php?order=123
        statusUrl: $container.data('status'),
        redirectTo: $container.data('redirect'),
        fiatCurrency: $container.data('currency-iso'),
        fiatSign: $container.data('currency-sign'),
        minOverpaymentFiat: $container.data('min-overpayment-fiat'),
        maxUnderpaymentFiat: $container.data('max-underpayment-fiat'),
        modal: false,
        enablePoweredBy: false
    });
});
