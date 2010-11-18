<h2><?php echo isset($data['id']) ? 'Edit Post' : 'New Post' ?></h2>
<form class="post" method="POST" action="<?php echo $this->get('actionUrl') ?>">
  <input type="hidden" name="csrf" value="<?php echo $data['csrf'] ?>" />
  <input type="hidden" name="id" value="<?php echo $data['id'] ?>" />
  <ul>
    <li class="row title">
      <label for="title">Title</label><input id="title" type="text" name="title" value="<?php echo $data['title'] ?>"/>
      <?php if ($this->error('title', 'required')): ?>
        <span class="error">Required.</span>
      <?php endif ?>
    </li>
    <li class="row body">
      <?php // Lone space ensures that we don't get "initial content" as an unhelpful default ?>
      <label for="body">Body</label><textarea id="body" name="body"><?php echo strlen($data['body']) ? $data['body'] : ' ' ?></textarea>
      <?php if ($this->error('body', 'required')): ?>
        <span class="error">Required.</span>
      <?php endif ?>
    </li>
    <script type="text/javascript" charset="utf-8">
      $(function() {
        $('.body textarea').wysiwyg({
          controls: {
            h1: { visible: false },
            h2: { visible: false },
            h4: { visible: true },
            h5: { visible: true },
            insertHorizontalRule: { visible: false },
            insertImage: { visible: false },
            justifyLeft: { visible: false },
            justifyCenter: { visible: false },
            justifyRight: { visible: false },
            justifyFull: { visible: false },
            indent: { visible: false },
            outdent: { visible: false },
            superscript: { visible: false },
            subscript: { visible: false }
          }
        });
      });
    </script>
    <li class="row">
      <ul class="publicity">
        <li class="row"><?php echo $this->radio('Everyone (Public)', 'publicity', 'public') ?></li>
        <li class="row"><?php echo $this->radio('All Friends', 'publicity', 'friends') ?></li>
        <li class="row some"><?php echo $this->radio('Certain Friends Only', 'publicity', 'some') ?>
        <span class="select-groups"><?php echo $this->multipleSelect('Select Groups of Friends', 'fgroups', $data['fgroupOptions']) ?></span></li>
        <li class="row"><?php echo $this->radio('Just Me (Private)', 'publicity', 'private') ?></li>
      </ul>
    </li>
    <li class="row">
      <ul class="controls">
        <li><input class="submit" type="submit" value="<?php echo isset($data['id']) ? 'Save' : 'Post' ?>" /></li>
        <li><a class="cancel" href="<?php echo $data['cancelUrl'] ?>">Cancel</a></li>
        <?php if (isset($data['deleteUrl'])): ?>
          <li>
            <a class="delete" href="<?php echo $data['deleteUrl'] ?>">Delete</a>
          </li>
        <?php endif ?>
      </ul>
    </li>
  </ul>
</form>
<script type="text/javascript" charset="utf-8">
  aMultipleSelect('.some', { 'remove': 'x', 'add': 'Add a New Group'});

  $('.delete').click(function() {
    return confirm('Are you sure you want to delete this post?');
  });
  
  $('ul.publicity input').change(function() {
    if ($('.some input:checked').length)
    {
      $('.some .select-groups').show();
    }
    else
    {
      $('.some .select-groups').hide();
    }
  });
  $('ul.publicity input').change();
</script>