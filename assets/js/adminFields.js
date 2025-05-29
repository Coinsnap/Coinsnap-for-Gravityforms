jQuery(document).ready(function ($) {
    
    if($('#coinsnap_provider').length){
        
        setProvider();
        
        $('#coinsnap_provider').change(function(){
            setProvider();
        });
    }
    
    function setProvider(){
        if($('#coinsnap_provider').val() === 'coinsnap'){
            $('.btcpay').closest('.gform-settings-field').hide();
            $('.btcpay').removeAttr('required');
            $('.coinsnap').closest('.gform-settings-field').show();
            $('.coinsnap').attr('required','required');
        }
        else {
            $('.coinsnap').closest('.gform-settings-field').hide();
            $('.coinsnap').removeAttr('required');
            $('.btcpay').closest('.gform-settings-field').show();
            $('.btcpay').attr('required','required');
        }
    }
    
    function isValidUrl(serverUrl) {
        try {
            const url = new URL(serverUrl);
            if (url.protocol !== 'https:' && url.protocol !== 'http:') {
                return false;
            }
	}
        catch (e) {
            console.error(e);
            return false;
	}
        return true;
    }

    $('.btcpay-apikey-link').click(function(e) {
        e.preventDefault();
        const host = $('#btcpay_server_url').val();
	if (isValidUrl(host)) {
            let data = {
                'action': 'btcpay_server_apiurl_handler',
                'host': host,
                'apiNonce': coinsnapgf_ajax.nonce
            };
            
            $.post(coinsnapgf_ajax.ajax_url, data, function(response) {
                if (response.data.url) {
                    window.location = response.data.url;
		}
            }).fail( function() {
		alert('Error processing your request. Please make sure to enter a valid BTCPay Server instance URL.')
            });
	}
        else {
            alert('Please enter a valid url including https:// in the BTCPay Server URL input field.')
        }
    });
});

