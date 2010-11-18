<h2 class="title">
  <?php echo $data['post']['title'] ?> 
  <?php if (isset($data['editUrl'])): ?>
    <a class="edit" href="<?php echo $data['editUrl'] ?>">Edit</a>
  <?php endif ?>
</h2>
<div class="post-body">
  <?php // Posts are prefiltered HTML, so in this one instance ?>
  <?php // we undo the escaping that occurs when data is passed ?>
  <?php // to a template ?>
  <?php echo html_entity_decode($data['post']['body']) ?>
</div>
