<section class="adeptix-crypto-instructions">
  <h3>{l s='Send your payment' mod='adeptix'}</h3>
  <p>
    {l s='Send exactly' mod='adeptix'}
    <strong>{$adeptix_crypto.amount|escape} {$adeptix_crypto.token|escape}</strong>
    {l s='on' mod='adeptix'} {$adeptix_crypto.chain|escape} {l s='to:' mod='adeptix'}
  </p>
  <p><code>{$adeptix_crypto.pay_to_address|escape}</code></p>
  <p>
    {l s='This address expires at' mod='adeptix'} {$adeptix_crypto.expires_at|escape}.
    {l s='Your order will update automatically once the deposit is confirmed.' mod='adeptix'}
  </p>
</section>
