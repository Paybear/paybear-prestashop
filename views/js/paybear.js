(function() {
    this.Paybear = function () {

        if (window.paybear instanceof Paybear) {
            paybear.destroy();
            window.paybear = undefined;
        }

        var that = this;
        var options;
        if (arguments[0] && typeof arguments[0] === "object") {
            this.arguments = arguments[0];
            options = this.arguments;
        }

        if (typeof options.button !== 'undefined') {
            var button = document.querySelector(options.button);

            if (!button) {
                throw new Error(
                    'Can\'t find ' + options.button
                );
            }

            var newButton = button.cloneNode(true);
            button.parentNode.replaceChild(newButton, button);

            newButton.addEventListener('click', function (e) {
                e.preventDefault();
                paybearInit.call(that);
            })
        } else {
            paybearInit.call(that);
        }

    };

    Paybear.prototype.destroy = function () {
        var that = this;
        var state = that.state;
        var appContainer = document.querySelector('.PayBear-app');

        appContainer.style.display = 'none';

        if (state.html) {
            if (that.options.modal && that.modal.parentNode) {
                document.body.appendChild(that.root);
                that.modal.parentNode.removeChild(that.modal);
            }
            that.root.innerHTML = state.html;
        }

        window.removeEventListener('resize', this.resizeListener, true);

        clearInterval(state.interval);
        clearInterval(state.checkStatusInterval);
    };

    function paybearInit() {
        this.state = {
            checkStatusInterval: null,
            interval: null,
            selected: 0,
            isConfirming: false,
            html: null,
            isModalShown: false
        };

        var defaults = {
            timer: 15 * 60,
            modal: true,
            fiatCurrency: 'USD',
            fiatSign: '$',
            enableFiatTotal: true,
            enablePoweredBy: false,
            enableBack: true,
            redirectTimeout: 5,
        };

        this.options = defaults;
        if (this.arguments) {
            this.options = extendDefaults(defaults, this.arguments);
        }

        var that = this;
        var options = that.options;

        this.resizeListener = function () {
            paybearResizeFont(that.state.currencies[that.state.selected]['address']);
        };

        that.root = document.getElementById('paybear');
        that.root.removeAttribute('style');

        that.state.html = that.root.innerHTML;

        if (options.settingsUrl) {
            if (options.modal) {
                initModal.call(that);
            }

            var xhr = new XMLHttpRequest();
            beforeCurrenciesSend.call(that);
            xhr.onload = function () {
                if (xhr.status !== 200) {
                    handleCurrenciesError.call(that);
                } else {
                    handleCurrenciesSuccess.call(that);
                    var response = JSON.parse(xhr.responseText);
                    Object.assign(options, response);
                    app.call(that);
                }
            };
            xhr.open('GET', options.settingsUrl, true);
            xhr.setRequestHeader('Content-Type', 'application/json');
            xhr.send();
        } else {
            app.call(that);
        }
    }

    function app() {
        var that = this;
        that.coinsBlock = document.querySelector('.PayBear__Icons');
        that.paymentBlock = document.querySelector('.P-Payment');
        that.paymentHeader = document.querySelector('.P-Payment__header');
        that.paymentHeaderTimer = document.querySelector('.P-Payment__header__timer');
        that.paymentHeaderTitle = document.querySelector('.P-Payment__header__title');
        that.paymentHeaderHelper = document.querySelector('.P-Payment__header__helper');
        that.topBackButton = document.querySelector('.PayBear__Nav__arrow');

        var state = that.state;
        var options = that.options;
        that.defaultTimer = options.timer;

        var appContainer = document.querySelector('.PayBear-app');
        appContainer.removeAttribute('style');

        if (options.modal && !options.settingsUrl) {
            initModal.call(that);
        }

        if (options.enableFiatTotal && options.fiatValue) {
            document.querySelector('.PayBear__Nav__price').removeAttribute('style');
            document.querySelector('.PayBear__Nav__price').innerHTML = options.fiatSign + '<span>' + options.fiatValue.toFixed(2) + '</span>' + options.fiatCurrency;
        }
        if (options.enablePoweredBy) {
            document.querySelector('.PayBear__brand-link').removeAttribute('style');
        }

        if (typeof options.currencies === 'string') {
            fetchCurrencies.call(that);
        } else if (Array.isArray(options.currencies)) {
            state.currencies = options.currencies;

            if (state.currencies.length > 1) {
                fillCoins.call(that);
            } else {
                if (state.currencies[state.selected].currencyUrl) {
                    currencyUrlXHR.call(that);
                } else {
                    paybearPaymentStart.call(that);
                }
            }
        } else {
            handleCurrenciesError.call(that);
            throw new Error(
                'Currencies is undefined'
            );
        }
    }

    function fetchCurrencies() {
        var that = this;
        var options = that.options;
        var state = that.state;

        beforeCurrenciesSend.call(that);
        var xhr = new XMLHttpRequest();
        xhr.onload = function () {
            if (xhr.status !== 200) {
                handleCurrenciesError.call(that);
            } else {
                try {
                    var response = JSON.parse(xhr.responseText);
                    if (Array.isArray(response)) {
                        handleCurrenciesSuccess();
                        var currencies = response;
                        state.currencies  = currencies;

                        if (currencies.length > 1) {
                            fillCoins.call(that);
                        } else {
                            paybearPaymentStart.call(that);
                        }

                    } else {
                        handleCurrenciesError.call(that);
                    }
                } catch(e) {
                    handleCurrenciesError.call(that);
                }
            }
        };
        xhr.open('GET', options.currencies, true);
        xhr.setRequestHeader('Content-Type', 'application/json');
        xhr.onerror = function () {
            handleCurrenciesError.call(that);
        };
        xhr.send();
    }

    function fillCoins() {
        var that = this;

        fillCoinsReset.call(that);

        var coinsContainer = that.coinsBlock;
        coinsContainer.innerHTML = '';

        that.state.currencies.map(function (item, index) {
            var classNames = ['PayBear__Item'];
            var coin = document.createElement('div');
            coin.setAttribute('role', 'button');
            coin.setAttribute('tabindex', 0);
            coin.className = classNames.join(' ');
            coin.onclick = function (e) {
                e.preventDefault();

                if (typeof that.options.onclick === 'function') {
                    console.log('Custom onclick:');
                    var result = that.options.onclick(item);
                    console.log('Custom onclick returned ', result);
                    if (!result) return;
                }

                if (item.currencyUrl) {
                    var xhr = new XMLHttpRequest();
                    beforeCurrencySend.call(that);
                    xhr.onload = function () {
                        if (xhr.status !== 200) {
                            handleCurrencyError.call(that);
                        } else {
                            try {
                                handleCurrencySuccess.call(that);
                                var response = JSON.parse(xhr.responseText);
                                Object.assign(that.state.currencies[index], response);

                                that.state.selected = index;
                                paybearPaymentStart.call(that);
                            } catch(e) {
                                handleCurrencyError.call(that);
                            }
                        }
                    };
                    xhr.open('GET', item.currencyUrl, true);
                    xhr.setRequestHeader('Content-Type', 'application/json');
                    xhr.onerror = function () {
                        handleCurrencyError.call(that);
                    };
                    xhr.send();
                } else {
                    that.state.selected = index;
                    paybearPaymentStart.call(that);
                }
            };

            coin.innerHTML = '<div class="PayBear__Item__icon">\n' +
                '<img src="' + item.icon + '" alt="' + item.title + '"></div>\n' +
                '<div class="PayBear__Item__code">' + item.code + '</div>\n' +
                '<div class="PayBear__Item__name">' + item.title + '</div>\n' +
                '<div class="PayBear__Item__val">' + (item.coinsValue ? item.coinsValue : '') + '</div>';

            coinsContainer.appendChild(coin);
        });

    }

    function fillCoinsReset() {
        var that = this;
        var state = that.state;
        var options = that.options;
        if (state.paymentsHtml) {
            document.querySelector('.P-box__inner').innerHTML = state.paymentsHtml;
        } else {
            state.paymentsHtml = document.querySelector('.P-box__inner').innerHTML;
        }

        if (options.modal) {
            that.topBackButton.removeAttribute('style');
            that.topBackButton.removeEventListener('click', that.handleTopBackButton);
            that.handleTopBackButton = function(event) {
                event.preventDefault();
                hideModal.call(that);
            };
            that.topBackButton.addEventListener('click', that.handleTopBackButton);
        } else if (options.onBackClick) {
            that.topBackButton.removeAttribute('style');
            that.topBackButton.removeEventListener('click', that.handleTopBackButton);
            that.handleTopBackButton = function(event) {
                event.preventDefault();
                paybearBack.call(that);
            };
            that.topBackButton.addEventListener('click', that.handleTopBackButton);
        } else {
            that.topBackButton.style.display = 'none';
        }


        that.paymentBlock.style.display = 'none';
        that.coinsBlock.removeAttribute('style');
        clearInterval(state.interval);
        clearInterval(state.checkStatusInterval);
        options.timer = that.defaultTimer;
        state.isConfirming = false;

        that.paymentHeader.className = 'P-Payment__header';
        that.paymentHeaderTitle.textContent = 'Waiting on Payment';
        that.paymentHeaderHelper.removeAttribute('style');
    }

    function paybearPaymentStart() {
        var that = this;
        var state = that.state;
        var options = that.options;

        if (!that.state.currencies[that.state.selected].address) {
            handleCurrencyError.call(that);
            throw new Error(
                'Currency address is undefined'
            );
        }

        // clear payments start events
        var temp = document.createElement('div');
        temp.innerHTML = state.html;
        var paymentStartScreenHTML = temp.querySelector('.P-Payment__start');
        var newPaymentStartScreen = paymentStartScreenHTML.cloneNode(true);
        var paymentStartScreen = document.querySelector('.P-Payment__start');
        paymentStartScreen.parentNode.replaceChild(newPaymentStartScreen, paymentStartScreen);

        if (state.currencies.length > 1) {
            that.topBackButton.removeAttribute('style');
            that.topBackButton.removeEventListener('click', that.handleTopBackButton);

            that.handleTopBackButton = function (event) {
                event.preventDefault();
                fillCoins.call(that);
            };
            that.topBackButton.addEventListener('click', that.handleTopBackButton);
        } else if (options.modal || options.onBackClick) {
            that.topBackButton.removeAttribute('style');
            that.topBackButton.removeEventListener('click', that.handleTopBackButton);

            that.handleTopBackButton = function (event) {
                event.preventDefault();
                paybearBack.call(that);
            };
            that.topBackButton.addEventListener('click', that.handleTopBackButton);
        }


        that.coinsBlock.style.display = 'none';
        that.paymentBlock.removeAttribute('style');

        var selectedCoin = state.currencies[state.selected];
        var rate = +selectedCoin.rate;
        var code = selectedCoin.code;
        that.paymentHeader.classList.remove('P-Payment__header--red');
        that.paymentHeaderTitle.textContent = 'Waiting on Payment';
        that.paymentHeaderHelper.innerHTML = 'Rate Locked 1 ' + code + ' : ' + options.fiatSign + formatMoney(rate, 2) + ' ' + options.fiatCurrency;
        that.paymentHeaderHelper.removeAttribute('style');

        // timer
        if (options.timer) {
            that.paymentHeaderTimer.textContent = formatTimer(options.timer);
            state.interval = setInterval(function() {
                var timer = options.timer - 1;
                if (timer < 1) {
                    that.paymentHeader.classList.add('P-Payment__header--red');
                    that.paymentHeaderTitle.textContent = 'Payment Window Expired';
                    that.paymentHeaderHelper.textContent = 'Rate Expired';
                    that.paymentHeaderHelper.removeAttribute('style');

                    paybearPaymentExpired.call(that);

                } else if (timer < 60) {
                    that.paymentHeader.classList.add('P-Payment__header--red');
                    that.paymentHeaderTitle.textContent = 'Window Expiring Soon';
                    that.paymentHeaderHelper.style.display = 'none';
                }
                if (timer >= 0) {
                    options.timer = timer;
                    that.paymentHeaderTimer.textContent = formatTimer(timer);
                } else {
                    clearInterval(state.interval);
                    clearInterval(state.checkStatusInterval);
                }
            }, 1000);
        } else {
            that.paymentHeaderTimer.style.display = 'none';
        }


        // coin icon
        var icon = document.querySelector('.P-Payment__value__icon img');
        icon.setAttribute('src', selectedCoin.icon);
        icon.setAttribute('alt', selectedCoin.title);

        // coin value
        var value = document.querySelector('.P-Payment__value__coins');
        value.textContent = selectedCoin.coinsValue + ' ' + selectedCoin.code;


        // qr code
        var qr = document.querySelector('.P-Payment__qr img');
        if (selectedCoin.walletLink) {
            selectedCoin.walletLink = selectedCoin.walletLink.replace(/%s(.+?)%s/, selectedCoin.address + '$1' + selectedCoin.coinsValue);
            qr.setAttribute('src', 'https://chart.googleapis.com/chart?chs=180x180&cht=qr&chl=' + encodeURIComponent(selectedCoin.walletLink));
        } else {
            qr.setAttribute('src', 'https://chart.googleapis.com/chart?chs=180x180&cht=qr&chl=' + encodeURIComponent(selectedCoin.address));
        }

        // wallet btn
        var walletBtn = document.querySelector('.P-wallet-btn');
        if (selectedCoin.metamask && typeof web3 !== 'undefined' && web3.eth.accounts.length) {
            console.log('metamask detected');
            var metamaskBtnText = 'Pay with MetaMask';
            walletBtn.querySelector('.P-btn-block__text').textContent = metamaskBtnText;
            walletBtn.addEventListener('click', function(e) {
                e.preventDefault();
                console.log('metamask invoked');
                var btn = this;
                btn.setAttribute('disabled', true);
                btn.querySelector('.P-btn-block__text').textContent = 'Loading';
                web3.eth.sendTransaction({
                    from: web3.eth.accounts[0],
                    to: selectedCoin.address,
                    value: web3.toWei(+selectedCoin.coinsValue)
                }, function(err, data) {
                    btn.removeAttribute('disabled');
                    btn.querySelector('.P-btn-block__text').textContent = metamaskBtnText;
                    if(err) {
                        console.log('metamask error');
                    } else {
                        console.log(data);
                        //success
                        paybearPaymentConfirming.call(that, 0);
                    }
                });
            });
            if (selectedCoin.metamaskAuto !== false) {
                walletBtn.click();
            }
        } else if (selectedCoin.walletLink) {
            walletBtn.setAttribute('href', selectedCoin.walletLink);
        } else {
            walletBtn.style.display = 'none';
        }

        paybearResizeFont(selectedCoin.address);
        window.addEventListener('resize', this.resizeListener, true);

        // copy address btn
        document.querySelector('.P-Payment__address__text').innerHTML = 'Please send ' + selectedCoin.title + ' to this Address';
        var copyAddress = document.querySelector('.P-btn-copy-address');
        copyAddress.querySelector('.P-btn-block__helper').innerHTML = selectedCoin.address.slice(0, 3) +' <span class="P-dots"><i></i></span> ' + selectedCoin.address.slice(-3);
        copyAddress.addEventListener('click', function () {
            copyAddress.classList.remove('P-btn-block--copied');
            copy.classList.remove('P-btn-block--copied');
            paybearCopyToClipboard(selectedCoin.address);
            copyAddress.classList.add('P-btn-block--copied');
        });

        // copy value btn
        var copy = document.querySelector('.P-Payment__value__copy');
        copy.querySelector('.P-btn-block__helper').innerHTML = selectedCoin.coinsValue;
        copy.addEventListener('click', function () {
            copyAddress.classList.remove('P-btn-block--copied');
            copy.classList.remove('P-btn-block--copied');
            paybearCopyToClipboard(selectedCoin.coinsValue);
            copy.classList.add('P-btn-block--copied');
        });

        // tabs
        paybearTabs.call(that);

        checkStatusXHR(options.statusUrl);

        function checkStatusXHR(statusUrl) {
            state.checkStatusInterval = setInterval(function () {
                var xhr = new XMLHttpRequest();
                xhr.onload = function () {
                    if (xhr.status === 200) {
                        var response = JSON.parse(xhr.responseText);
                        if ((typeof response.confirmations === 'number' || typeof response.confirmations === 'string') && !Number.isNaN(+response.confirmations)) {
                            paybearPaymentConfirming.call(that, response.confirmations);
                        }

                        if (response.statusUrl) {
                            var parser = document.createElement('a');
                            parser.href = options.statusUrl;
                            clearInterval(state.checkStatusInterval);
                            checkStatusXHR(parser.protocol + '//' + parser.host + response.statusUrl);
                        }

                        if (response.success) {
                            clearInterval(state.checkStatusInterval);
                            paybearPaymentConfirmed.call(that, response.redirect_url);
                        }
                    }
                };
                xhr.open('GET', statusUrl, true);
                xhr.setRequestHeader('Content-Type', 'application/json');
                xhr.send();
            }, 10000);
        }
    }

    function paybearPaymentExpired() {
        var that = this;
        var options = that.options;
        var state = that.state;
        clearInterval(state.interval);
        clearInterval(state.checkStatusInterval);
        var paymentExpired = document.querySelector('.P-Payment__expired');
        var paymentStart = document.querySelector('.P-Payment__start');
        paymentStart.style.display = 'none';
        paymentExpired.removeAttribute('style');

        // helper
        var showPaymentHelper = paymentExpired.querySelector('.P-Payment__helper');
        var paymentHelper = document.querySelector('.P-Payment__expired-helper');
        var paymentHelperBtn = document.querySelector('.P-Payment__expired-helper button');
        showPaymentHelper.addEventListener('click', function () {
            paymentExpired.style.display = 'none';
            paymentHelper.removeAttribute('style');
        });
        paymentHelperBtn.addEventListener('click', function () {
            paymentExpired.removeAttribute('style');
            paymentHelper.style.display = 'none';
        });

        paymentExpired.querySelector('.P-btn').addEventListener('click', function retry(e) {
            e.preventDefault();

            paymentStart.removeAttribute('style');
            paymentExpired.style.display = 'none';
            options.timer = that.defaultTimer;
            if (state.currencies[state.selected].currencyUrl) {
                currencyUrlXHR.call(that);
            } else {
                paybearPaymentStart.call(that);
            }
            this.removeEventListener('click', retry);
        });
    }

    function paybearPaymentConfirming(confirmations) {
        var that = this;
        var options = that.options;
        var state = that.state;
        var isConfirming = state.isConfirming;
        var selectedCoin = state.currencies[state.selected];
        var coinConfirmations = selectedCoin.maxConfirmations;

        if (!isConfirming) {
            if (options.modal) {
                that.topBackButton.removeAttribute('style');
                that.topBackButton.removeEventListener('click', that.handleTopBackButton);

                that.handleTopBackButton = function (event) {
                    event.preventDefault();
                    hideModal.call(that);
                };
                that.topBackButton.addEventListener('click', that.handleTopBackButton);
            } else {
                that.topBackButton.style.display = 'none';
            }

            that.paymentHeader.classList.remove('P-Payment__header--red');
            window.removeEventListener('resize', that.resizeListener, true);
            clearInterval(state.interval);

            var timer = 0;
            that.paymentHeaderTimer.removeAttribute('style');
            that.paymentHeaderTimer.textContent = formatTimer(timer);
            this.state.interval = setInterval(function () {
                ++timer;
                that.paymentHeaderTimer.textContent = formatTimer(timer);
            }, 1000);

            var paymentStart = document.querySelector('.P-Payment__start');
            var paymentConfirming = document.querySelector('.P-Payment__confirming');
            paymentStart.style.display = 'none';
            paymentConfirming.removeAttribute('style');

            // helper
            var showPaymentHelper = paymentConfirming.querySelector('.P-Payment__helper');
            var paymentHelper = document.querySelector('.P-Payment__confirming-helper');
            var paymentHelperBtn = document.querySelector('.P-Payment__confirming-helper button');
            showPaymentHelper.addEventListener('click', function () {
                var blockExplorer = state.currencies[state.selected].blockExplorer;
                paymentConfirming.style.display = 'none';
                paymentHelper.removeAttribute('style');
                if (paymentHelper.clientHeight > document.querySelector('.P-box__inner').clientHeight) {
                    paymentHelper.style.overflowY = 'scroll';
                }
                if (blockExplorer) {
                    var blockExplorerLink = blockExplorer.replace(/%s/g, selectedCoin.address);
                    paymentHelper.querySelector('.block-explorer-li').style.display = 'block';
                    paymentHelper.querySelector('.P-block-explorer').setAttribute('href', blockExplorerLink);
                }
            });
            paymentHelperBtn.addEventListener('click', function () {
                paymentConfirming.removeAttribute('style');
                paymentHelper.style.display = 'none';
            });

            //header
            that.paymentHeaderTitle.textContent = 'Confirming Payment';

            document.querySelector('.P-confirmations')
                .innerHTML = 'Payment Detected. Waiting for ' + coinConfirmations +
                (coinConfirmations === 1 ? ' Confirmation' : ' Confirmations');

            if (options.modal) {
                paymentConfirming.querySelector('.P-btn').addEventListener('click', function (e) {
                    e.preventDefault();
                    hideModal.call(that);
                });
            } else {
                paymentConfirming.querySelector('.P-btn').style.display = 'none';
            }
        }

        that.paymentHeaderHelper.textContent = confirmations + ' / ' + coinConfirmations + (coinConfirmations === 1 ? ' Confirmation' : ' Confirmations');
        document.querySelector('.Confirming__icon').classList.value = 'Confirming__icon' + (coinConfirmations < 4 ? ' Confirming__icon--small' : '') + (coinConfirmations > 4 ? ' Confirming__icon--full' : '');
        document.querySelector('.Confirming__icon svg').classList.value = 'Confirming__pic Confirming__pic--' + confirmations;
        this.state.isConfirming = true;
    }

    function paybearPaymentConfirmed(redirect) {
        var that = this;
        var state = that.state;
        var options = that.options;
        var selectedCoin = state.currencies[state.selected];
        clearInterval(state.interval);
        clearInterval(state.checkStatusInterval);

        var paymentStart = document.querySelector('.P-Payment__start');
        var paymentConfirming = document.querySelector('.P-Payment__confirming');
        var paymentConfirmed = document.querySelector('.P-Payment__confirmed');
        paymentStart.style.display = 'none';
        paymentConfirming.style.display = 'none';
        paymentConfirmed.removeAttribute('style');

        //header
        var coinConfirmations = +selectedCoin.maxConfirmations;
        that.paymentHeader.classList.remove('P-Payment__header--red');
        that.paymentHeader.classList.add('P-Payment__header--green');
        that.paymentHeaderTitle.textContent = 'Payment Confimed';
        that.paymentHeaderHelper.textContent = coinConfirmations + (coinConfirmations === 1 ? ' Confirmation' : ' Confirmations') + ' found';
        that.paymentHeaderTimer.style.display = 'none';
        document.querySelector('.P-Payment__header__check').style.display = 'block';

        if (options.redirectTo && options.redirectTimeout) {
            paymentConfirmed.querySelector('p').textContent = 'Redirecting you back in ' + options.redirectTimeout + ' seconds.';
            paymentConfirmed.querySelector('.P-btn').setAttribute('href', options.redirectTo);
            if (options.enableBack) {
                that.topBackButton.removeEventListener('click', that.handleTopBackButton);
                that.topBackButton.setAttribute('href', options.redirectTo);
            }
        } else if (options.redirectTo) {
            paymentConfirmed.querySelector('p').style.display = 'none';
            paymentConfirmed.querySelector('.P-btn').setAttribute('href', options.redirectTo);
            if ((options.enableBack && options.onBackClick) || options.modal) {
                that.topBackButton.removeEventListener('click', that.handleTopBackButton);
                that.topBackButton.addEventListener('click', function (e) {
                    e.preventDefault();
                    paybearBack.call(that);
                });
            }
        } else {
            paymentConfirmed.querySelector('p').style.display = 'none';
            if (options.modal) {
                var btn = paymentConfirmed.querySelector('.P-btn');
                var newBtn = document.createElement('button');
                newBtn.innerHTML = '<i class="P-btn__icon--close"></i> Close';
                newBtn.classList.value = 'P-btn P-btn--sm';
                btn.parentNode.replaceChild(newBtn, btn);
                paymentConfirmed.querySelector('.P-btn').addEventListener('click', function (e) {
                    e.preventDefault();
                    hideModal.call(that);
                });
                that.topBackButton.removeEventListener('click', that.handleTopBackButton);
                that.topBackButton.addEventListener('click', function (e) {
                    e.preventDefault();
                    hideModal.call(that);
                });
            } else {
                that.topBackButton.style.display = 'none';
                paymentConfirmed.querySelector('.P-btn').style.display = 'none';
            }
        }

        if ((options.redirectTo && options.redirectTimeout) || redirect) {
            if (options.redirectTo) {
                redirect = options.redirectTo;
            }

            setTimeout(function () {
                window.location.href = redirect;
                if (redirect.indexOf(window.location.href) > -1) {
                    window.location.reload();
                }
            }, options.redirectTimeout * 1000);
        }

    }

    function extendDefaults(source, properties) {
        var property;
        for (property in properties) {
            if (properties.hasOwnProperty(property)) {
                source[property] = properties[property];
            }
        }
        return source;
    }

    function formatTimer(timer) {
        var minutes = parseInt(timer / 60, 10);
        var seconds = parseInt(timer % 60, 10);
        minutes = minutes < 10 ? '0' + minutes : minutes;
        seconds = seconds < 10 ? '0' + seconds : seconds;
        return minutes + ':' + seconds;
    }

    function paybearCopyToClipboard(text) {
        if (window.clipboardData && window.clipboardData.setData) {
            // IE specific code path to prevent textarea being shown while dialog is visible.
            return clipboardData.setData('Text', text);
        } else if (document.queryCommandSupported && document.queryCommandSupported('copy')) {
            var textarea = document.createElement('textarea');

            // Prevent zooming on iOS
            textarea.style.fontSize = '12pt';
            // Reset box model
            textarea.style.border = '0';
            textarea.style.padding = '0';
            textarea.style.margin = '0';
            // Move element out of screen horizontally
            textarea.style.position = 'absolute';
            textarea.style.left = '-9999px';
            // Move element to the same position vertically
            var yPosition = window.pageYOffset || document.documentElement.scrollTop;
            textarea.style.top = yPosition + 'px';

            textarea.setAttribute('readonly', '');
            textarea.value = text;

            document.querySelector('.PayBear-app').appendChild(textarea);

            selectByElement(textarea);

            try {
                return document.execCommand('copy');  // Security exception may be thrown by some browsers.
            } catch (ex) {
                console.warn('Copy to clipboard failed.', ex);
                return false;
            }
        }
    }

    function paybearTabs() {
        var state = this.state;
        var selectedCoin = state.currencies[state.selected];
        var tabsClassNames = {
            list: 'P-Tabs__Tab-list',
            listSecond: 'P-Tabs__Tab-list--second',
            listThird: 'P-Tabs__Tab-list--third',
            listNoWallet: 'P-Tabs__Tab-list--no-wallet',
            tab: 'P-Tabs__Tab',
            tabWallet: 'P-Tabs__Tab--wallet',
            panel: 'P-Tabs__Tab-panel',
            panelWallet: 'P-Tabs__Tab-panel--wallet',
            selectedTab: 'P-Tabs__Tab--selected',
            selectedPanel: 'P-Tabs__Tab-panel--selected',
        };
        var tabs = document.querySelectorAll('.' + tabsClassNames.tab);
        var tabPanels = document.querySelectorAll('.' + tabsClassNames.panel);

        if (!selectedCoin.walletLink) {
            document.querySelector('.' + tabsClassNames.list).classList.remove(tabsClassNames.listSecond);
            document.querySelector('.' + tabsClassNames.list).classList.add(tabsClassNames.listNoWallet);
            document.querySelector('.' + tabsClassNames.tabWallet).style.display = 'none';
            document.querySelector('.' + tabsClassNames.panelWallet).style.display = 'none';
            tabs = document.querySelectorAll('.' + tabsClassNames.tab + ':not(.' + tabsClassNames.tabWallet + ')');
            tabPanels = document.querySelectorAll('.' + tabsClassNames.panel + ':not(.' + tabsClassNames.panelWallet + ')');
        }

        for (var i = 0; i < tabs.length; ++i) {
            (function(index){
                tabs[i].onclick = function() {
                    var tabListClassNames = [tabsClassNames.list];
                    if (!selectedCoin.walletLink) {
                        tabListClassNames.push(tabsClassNames.listNoWallet);
                    }
                    if (index === 1) {
                        tabListClassNames.push(tabsClassNames.listSecond)
                    } else if (index === 2) {
                        tabListClassNames.push(tabsClassNames.listThird)
                    }
                    this.parentNode.className = tabListClassNames.join(' ');

                    for (var k = 0; k < tabs.length; ++k) {
                        tabs[k].className = tabsClassNames.tab;
                    }
                    this.classList.add(tabsClassNames.selectedTab);

                    for (var l = 0; l < tabPanels.length; ++l) {
                        tabPanels[l].classList.remove(tabsClassNames.selectedPanel);
                    }
                    tabPanels[index].classList.add(tabsClassNames.selectedPanel);
                }
            })(i);
        }
    }

    function paybearBack() {
        var that = this;
        var options = that.options;
        var onBackClick = options.onBackClick;
        if (onBackClick) {
            if (typeof onBackClick === 'string') {
                window.location = onBackClick;
                return false;
            }
            onBackClick();
        } else if (options.modal) {
            hideModal.call(that);
        }
    }

    function selectByElement(element) {
        var selectedText;

        if (element.nodeName === 'SELECT') {
            element.focus();

            selectedText = element.value;
        }
        else if (element.nodeName === 'INPUT' || element.nodeName === 'TEXTAREA') {
            var isReadOnly = element.hasAttribute('readonly');

            if (!isReadOnly) {
                element.setAttribute('readonly', '');
            }

            element.select();
            element.setSelectionRange(0, element.value.length);

            if (!isReadOnly) {
                element.removeAttribute('readonly');
            }

            selectedText = element.value;
        }
        else {
            if (element.hasAttribute('contenteditable')) {
                element.focus();
            }

            var selection = window.getSelection();
            var range = document.createRange();

            range.selectNodeContents(element);
            selection.removeAllRanges();
            selection.addRange(range);

            selectedText = selection.toString();
        }

        return selectedText;
    }

    function formatMoney(n, c, d, t) {
        var c = isNaN(c = Math.abs(c)) ? 2 : c,
            d = d == undefined ? "." : d,
            t = t == undefined ? "," : t,
            s = n < 0 ? "-" : "",
            i = String(parseInt(n = Math.abs(Number(n) || 0).toFixed(c))),
            j = (j = i.length) > 3 ? j % 3 : 0;
        return s + (j ? i.substr(0, j) + t : "") + i.substr(j).replace(/(\d{3})(?=\d)/g, "$1" + t) + (c ? d + Math.abs(n - i).toFixed(c).slice(2) : "");
    }

    function paybearResizeFont(address) {
        var addressContainerWidth = document.querySelector('.P-Payment__address').clientWidth;
        var addressCode = document.querySelector('.P-Payment__address code');
        // detecting computed letter width + offset
        addressCode.innerHTML = '<span>A</span>';
        var addressCodeSpan = addressCode.querySelector('span');
        addressCodeSpan.style.display = 'inline-block';
        var letterWidth = parseFloat(window.getComputedStyle(addressCodeSpan, null).width);
        addressCode.innerHTML = '';
        letterWidth = letterWidth + (letterWidth * 0.06);
        // computing font-size
        var addressLength = address.split('').length;
        var addressLetterWidth = addressContainerWidth / addressLength;
        var currentFontSize = parseInt(window.getComputedStyle(addressCode, null).fontSize);
        var fontSize = Math.ceil(addressLetterWidth * currentFontSize / letterWidth);
        addressCode.style.fontSize = (fontSize > 20 ? 20 : fontSize) + 'px';

        addressCode.textContent = address;
    }

    function beforeCurrenciesSend() {
        document.querySelector('.PayBear-spinner').removeAttribute('style');
        document.querySelector('.PayBear-app').style.display = 'none';
        document.querySelector('.PayBear-app-error').style.display = 'none';
    }

    function handleCurrenciesError() {
        var that = this;
        document.querySelector('.PayBear-spinner').style.display = 'none';
        document.querySelector('.PayBear-app').style.display = 'none';
        document.querySelector('.PayBear-app-error').removeAttribute('style');
        if (typeof that.options.currencies === 'string') {
            document.querySelector('.PayBear-app-error .P-btn').addEventListener('click', function retry(e) {
                e.preventDefault();
                fetchCurrencies.call(that);
                this.removeEventListener('click', retry);
            });
        } else {
            document.querySelector('.PayBear-app-error .P-btn').style.display = 'none';
        }

        if (that.options.modal) {
            handleModalOverlay.call(that);
        }
    }

    function handleCurrenciesSuccess() {
        document.querySelector('.PayBear-spinner').style.display = 'none';
        document.querySelector('.PayBear-app').removeAttribute('style');
    }

    function beforeCurrencySend() {
        var that = this;
        that.coinsBlock.classList.add('P-disabled');
    }

    function handleCurrencyError() {
        var that = this;
        that.coinsBlock.classList.remove('P-disabled');
        document.querySelector('.PayBear-app').style.display = 'none';
        document.querySelector('.PayBear-app-error').removeAttribute('style');

        if (that.state.currencies.length > 1) {
            document.querySelector('.PayBear-app-error .P-btn').textContent = 'Back';
            document.querySelector('.PayBear-app-error .P-btn').addEventListener('click', function retry(e) {
                e.preventDefault();
                if (typeof that.options.currencies === 'string') {
                    fetchCurrencies.call(that);
                } else {
                    document.querySelector('.PayBear-app').removeAttribute('style');
                    document.querySelector('.PayBear-app-error').style.display = 'none';
                    fillCoins.call(that);
                }
                this.removeEventListener('click', retry);
            });
        } else {
            document.querySelector('.PayBear-app-error .P-btn').style.display = 'none';
        }

        if (that.options.modal) {
            handleModalOverlay.call(that);
        }
    }

    function handleCurrencySuccess() {
        var that = this;
        that.coinsBlock.classList.remove('P-disabled');
    }

    function currencyUrlXHR() {
        var that = this;
        var state = that.state;
        var xhr = new XMLHttpRequest();
        beforeCurrenciesSend.call(that);
        xhr.onload = function () {
            if (xhr.status !== 200) {
                handleCurrencyError.call(that);
            } else {
                handleCurrenciesSuccess.call(that);
                var response = JSON.parse(xhr.responseText);
                Object.assign(state.currencies[state.selected], response);
                paybearPaymentStart.call(that);
            }
        };
        xhr.open('GET', state.currencies[state.selected].currencyUrl, true);
        xhr.setRequestHeader('Content-Type', 'application/json');
        xhr.send();
    }

    // modal
    function initModal() {
        var that = this;
        var modal = document.createElement('div');
        modal.className = 'PayBearModal';
        var overlay = document.createElement('div');
        overlay.className = 'PayBearModal__Overlay';
        var modalContent = document.createElement('div');
        modalContent.className = 'PayBearModal__Content';
        modalContent.appendChild(that.root);
        modal.appendChild(overlay);
        modal.appendChild(modalContent);
        document.body.appendChild(modal);
        that.modal = modal;
        showModal.call(that);
    }

    function showModal() {
        var that = this;

        that.state.isModalShown = true;

        var event = document.createEvent('HTMLEvents');
        event.initEvent('show', true, true);
        event.eventName = 'show';
        that.modal.dispatchEvent(event);

        updateModal.call(that);
    }

    function hideModal() {
        var that = this;

        that.state.isModalShown = false;

        var event = document.createEvent('HTMLEvents');
        event.initEvent('hide', true, true);
        event.eventName = 'hide';
        that.modal.dispatchEvent(event);

        updateModal.call(that);

        that.destroy();
    }

    function updateModal() {
        var that = this;
        var openedModalClass = 'PayBearModal--open';
        var openedModalBodyClass = 'PayBearModal__Body--open';

        if (that.state.isModalShown) {
            that.modal.classList.add(openedModalClass);
            document.body.classList.add(openedModalBodyClass);
        } else {
            that.modal.classList.remove(openedModalClass);
            document.body.classList.remove(openedModalBodyClass);
        }
    }

    function handleModalOverlay() {
        var that = this;
        var overlay = document.querySelector('.PayBearModal__Overlay');
        var newOverlay = overlay.cloneNode(true);

        overlay.parentNode.replaceChild(newOverlay, overlay);

        newOverlay.addEventListener('click', function errorClose() {
            hideModal.call(that);
            this.removeEventListener('click', errorClose, false);
        }, false);
    }

}());