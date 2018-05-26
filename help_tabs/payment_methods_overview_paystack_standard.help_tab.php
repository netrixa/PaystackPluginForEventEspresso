<p>
    <strong><?php _e('Paystack Checkout', 'event_espresso'); ?></strong>
</p>
<p>
    <?php _e('Please be sure to update the settings for the Paystack  Checkout payment method.', 'event_espresso'); ?>
</p>
<p>
    <?php printf(__('For more information on how to get your API credentials, please view the %1$sPaystack Documentation%2$s.', 'event_espresso'), '<a target="_blank" href="https://developers.paystack.co/docs">', '</a>'); ?>
</p>


<p>
    <strong><?php _e('Paystack  Checkout Settings', 'event_espresso'); ?></strong>
</p>
<ul>

    <li>
        <strong><?php _e('API Primary Key', 'event_espresso'); ?></strong><br/>
        <?php _e('Your Paystack API Primary Key.'); ?>
    </li>

</ul>

<p>
Confirm that your server can conclude a TLSv1.2 connection to Paystack's servers. Most up-to-date software have this capability. Contact your service provider for guidance if you have any SSL errors.
</p>