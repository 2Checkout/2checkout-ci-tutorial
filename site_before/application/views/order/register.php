 <div class="container">

      <h1>Register</h1>
      <p>Please setup your user details below.</p>

      <div id="infoMessage"><?php echo $message;?></div>

      <?php echo form_open("order/register");?>
            <p>
                  First Name: <br />
                  <?php echo form_input($first_name);?>
            </p>

            <p>
                  Last Name: <br />
                  <?php echo form_input($last_name);?>
            </p>

            <p>
                  Email: <br />
                  <?php echo form_input($email);?>
            </p>

            <p>
                  Password: <br />
                  <?php echo form_input($password);?>
            </p>

            <p>
                  Confirm Password: <br />
                  <?php echo form_input($password_confirm);?>
            </p>

            <p><button type="submit" class="btn btn-primary btn-large">Register</button></p>

      <?php echo form_close();?>
</div>