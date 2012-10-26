<div class="container">
	<h1>Forgot Password</h1>
	<p>Please enter your email address so we can send you an email to reset your password.</p>

	<div id="infoMessage"><?php echo $message;?></div>

	<?php echo form_open("auth/forgot_password");?>

	      <p>
	      	Email Address: <br />
	      	<?php echo form_input($email);?>
	      </p>
	      
	      <p>
	      	<button type="submit" class="btn btn-primary btn-large">Submit</button>
	      </p>
	      
	<?php echo form_close();?>
</div>