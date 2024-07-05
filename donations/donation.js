document.addEventListener('DOMContentLoaded', function() {
    var stripe = Stripe('pk_redacted_for_obvious_reasons');
    var elements = stripe.elements();
    var card = elements.create('card');
    card.mount('#card-element');

    var form = document.getElementById('payment-form');
    form.addEventListener('submit', function(event) {
        event.preventDefault();

        var amount = document.getElementById('amount').value;
        var comment = document.getElementById('comment').value;

        fetch('donation.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'amount=' + amount + '&comment=' + encodeURIComponent(comment)
        })
        .then(function(response) { return response.json(); })
        .then(function(data) {
            if (data.error) {
                console.error(data.error);
                return;
            }
            return stripe.confirmCardPayment(data.clientSecret, {
                payment_method: { card: card }
            });
        })
        .then(function(result) {
            if (result.error) {
                console.error(result.error.message);
            } else {
                alert('Payment successful! Thank you for your donation.');
                form.reset();
            }
        });
    });
});