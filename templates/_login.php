<h2>Log In</h2>
<form method="POST" action="<?php echo $this->get('actionUrl') ?>">
  <input type="hidden" name="csrf" value="<?php echo $this->get('csrf') ?>" />
  <ul>
    <li>
      <?php if ($this->error('password', 'required')): ?>
        <span class="error">Required.</span>
      <?php endif ?>
      <?php if ($this->error('password', 'incorrect')): ?>
        <span class="error">Incorrect.</span>
      <?php endif ?>
      <label for="password">Password</label><input type="password" name="password" value="" />
    </li>
    <li>
      <input class="submit" type="submit" value="Log In" /> <a class="cancel" href="<?php echo $data['cancelUrl'] ?>">Cancel</a>
    </li>
  </ul>
</form>