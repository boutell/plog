<h2 class="title">
  <?php echo $data['post']['title'] ?> 
  <?php if (isset($data['editUrl'])): ?>
    <a class="edit" href="<?php echo $data['editUrl'] ?>">Edit</a>
  <?php endif ?>
</h2>
<div class="post-body">
<?php echo $data['post']['body'] ?>
</div>
