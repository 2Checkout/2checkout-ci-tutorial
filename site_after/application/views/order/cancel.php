<div class="container">

      <h1>Cancel Membership</h1>
      <p>Are you sure you want to cancel?</p>
      <?php echo form_open("order/cancel");?>
            <input type="hidden" name="cancel" value="true">
            <p><button type="submit" class="btn btn-primary btn-large">Cancel Subscription</button></p>
      <?php echo form_close();?>
</div>