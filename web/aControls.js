// Changes for Plog so far:
// * Implemented swiping the label from the associated label element as a better default behavior.
// No more taking the first option as the label by default.
// * Removed some Apostrophe-specific markup.

// Copyright 2009 P'unk Avenue LLC. Released under the MIT license.
// See http://www.symfony-project.org/plugins/apostrophePlugin and
// http://www.punkave.com/ for more information.

// aMultipleSelect transforms multiple selection elements into
// attractive, user-friendly replacements. 

// aRadioSelect transforms single-select elements into a set of pill 
// buttons (you probably won't always want this, it's handy when used 
// selectively). 

// aSelectToList transforms single-select elements into
// <ul> lists in which the list items set the underlying, hidden
// select element and then immediately submit the form. There is also
// special support for lists of tags with a count in parentheses
// following the tag name in the label, like so: 
//
// <option value="foo">foo (5)</option>

// Now accepts options. Valuable since you probably want to set the
// choose-one option to something like "Select to Add" in a
// progressive-enhancement scenario where you haven't manually provided
// a first option that is actually a label

// This adds support for indexOf to browsers that are missing this functionality (IE)
// https://developer.mozilla.org/En/Core_JavaScript_1.5_Reference:Objects:Array:indexOf#Compatibility
if (!Array.prototype.indexOf)
{
  Array.prototype.indexOf = function(elt /*, from*/)
  {
    var len = this.length >>> 0;

    var from = Number(arguments[1]) || 0;
    from = (from < 0)
         ? Math.ceil(from)
         : Math.floor(from);
    if (from < 0)
      from += len;

    for (; from < len; from++)
    {
      if (from in this &&
          this[from] === elt)
        return from;
    }
    return -1;
  };
}

function aMultipleSelectAll(options)
{
	if (options === undefined)
	{
		options = {};
	}
  $(document).ready(
    function() {
      aMultipleSelect('body', options);
    }
  );
}

// Transforms multiple select elements into a much more attractive
// and user-friendly control with a pulldown menu to add the next item and
// links to remove already-selected choices. 
//
// By default the first item becomes the label and can't be selected. This 
// is lousy progressive enhancement, so set the 'choose-one' option to a 
// better label and your first option is selectable again.
//
// Sometimes you want users to be able to create new options on the fly.
// To support that, specify the 'add' option with its value set to the label
// for an option users choose to add a new value. Now
// users can add entirely new string values, which appear as an array of
// checkboxes named originalname_add[]. However, if the original element name
// involves brackets, aMultipleSelect will do the right thing to create
// a peer at the same level ending with _add. For instance, if the 
// original name is thingy[myselect], the add checkbox array will be
// named thingy[myselect_add][].
//
// You can relabel the Add and Cancel buttons with the add-add-label and
// add-cancel-label options.
//
// Sometimes a multiple select element is conceptually correct but there
// are too many valid options to display, or even download. For this situation
// try the 'autocomplete' option. This option takes a callback URL as a parameter.
// The user is shown a textfield instead of a select menu. This textfield
// uses jQuery UI autocomplete to allow the user to build up a list of choices
// in an otherwise normal manner without ever seeing a pulldown of every
// possible choice. The user's input is posted to the callback URL in a
// parameter called 'term'. The callback URL should reply with a JSON-encoded 
// flat array of objects like so:
//
// [ { label: 'Foo', value: 1 }, { label: 'Bar', value: 2 } ]
//
// aMultipleSelect will display the specified labels and submit the specified values.
// Note that this is easy to arrange with json_encode in PHP.
//
// TODO: post back the selections already made so redundant suggestions are
// not made.
//
// The autocomplete and "add" options cannot currently be used together.
//
// TODO: we have a bunch of Apostrophe-specific class names in here including
// stuff that is not namespaced. Shame. Must clean up

function aMultipleSelect(target, options)
{
	if (options === undefined)
	{
		options = {};
	}
	
  $(target).find('select[multiple]').each(
    function(i) {
      var name = $(this).attr('name');
      var id = $(this).attr('id');
      var values = [];
      var labels = [];
      var selected = [];
      var j;
			var autocomplete = false;
			if (options['autocomplete'] !== undefined)
			{
				autocomplete = options['autocomplete'];
			}

			// You can set the label in two ways: by specifying the
			// choose-one option when calling, or by allowing 
			// aMultipleSelect to snag the relevant label element,
			// which must exist
			
			var label;
			if (options['choose-one'])
			{
				label = options['choose-one'];
			}
			else
			{
				var labelElement = $('label[for=' + id + ']');
				label = labelElement.text();
				labelElement.remove();
			}
			values.push('');
			labels.push(label);
			selected.push(false);
			
      for (j = 0; (j < this.options.length); j++)
      {
        var option = this.options[j];
        values.push(option.value);
        labels.push(option.innerHTML);
        // Firefox is a little cranky about this,
        // try it both ways
        selected.push(option.getAttribute('selected') || option.selected);
      }

			var length = values.length;
			
			var addIndex = undefined;
			if (options['add']) {
				var addIndex = values.length;
				values.push('_new');
				labels.push(options['add']);
				selected.push(false);
				var addName = name + '_add';
				if (name.substr(name.length - 3) === '][]')
				{
					addName = name.substr(0, name.length - 3) + '_add][]';
				}
				else if (name.substr(name.length - 2) === '[]')
				{
					addName = name.substr(0, name.length - 2) + '_add[]';
				}
				else if (name.substr(name.length - 1) === ']')
				{
					addName = name.substr(0, name.length - 1) + '_add]';
				}
			}
			
      if (id === '')
      {
      	// We need a unique ID for the element, give it one
      	id = 'a_id_' + Math.floor(Math.random() * 1000000000);
      }
      var html = "<div class='a-multiple-select' id='" + id + "'>";

			if (options['add-add-label'] === undefined)
			{
				options['add-add-label'] = 'Add';
			}
			
			if (autocomplete)
			{
				html += '<div class="a-autocomplete">\n';
				html += "<input type='text' />";
// 				html += '<a href="#add" onclick="return false;" class="autocomplete-add a-btn icon a-add"><span class="icon"></span>' + options['add-add-label'] + '</a>\n';
				html += '</div>\n';
			}
			else
			{
	      html += "<select class='a-multiple-select-input' ";
				html += "name='select-" + name + "'></select>\n";
			}
			if (addIndex !== undefined)
			{
				if (options['add-cancel-label'] === undefined)
				{
					options['add-cancel-label'] = 'Cancel';
				}
				html += '<div class="add" style="display: none">\n';
				html += '<input name="add-text" class="add-text">\n';
				html += '<a href="#add" onclick="return false;" class="add-add a-btn icon a-add"><span class="icon"></span>' + options['add-add-label'] + '</a>\n';
				html += '<a href="#cancel" onclick="return false;" class="a-btn icon a-cancel add-cancel no-label"><span class="icon"></span>' + options['add-cancel-label'] + '</a>\n';
				html += '</div>\n';
			}
      for (j = 0; (j < length); j++)
      {
        html += "<input type='checkbox' name='" + name + "'";

        if (options['class-name'] !== undefined)
        {
        	html += "class='" + options['class-name'] + "'";
        }

        if (selected[j])
        {
          html += " checked";
        }
        html += " value=\"" + aHtmlEscape(values[j]) + 
          "\" style='display: none'/>";
      }

			// Generate remover links
			
      html += "<ul class='a-ui a-multiple-select-list'>";
      if (!options['remove'])
      {
        options['remove'] = ' <span class="icon"></span><span>Remove</span>';
      }
      for (j = 0; (j < length); j++)
      {
        html += liHtml(labels[j], options);
      }
      html += "</ul>\n";

      // Handy for clearing floats
      html += "<div class='a-multiple-select-after'></div>\n";
      html += "</div>\n";
      $(this).replaceWith(html);
			var container = $('#' + id);
			container.find('.add-cancel').click(function() {
				container.find('.add').hide();
				return false;
			});
			container.find('.add-add').click(function() {
				doSaveAdd();
			});
			container.find('.add-text').keypress(function(event){
				if (event.keyCode == '13') {
					event.preventDefault(); 									
					doSaveAdd();					
				};
			});
			function doSaveAdd()
			{
				container.find('.add').hide();
				var addText = container.find('.add-text');
				var v = addText.val();
				addText.val('');
				var ev = aHtmlEscape(v);
				if (v.length && (!containsLabel(v)))
				{
					container.append("<input type='checkbox' name='" + addName + "' value='" + ev + "' style='display: none' checked />"); 
					var remover = $(liHtml(v, options));
					remover.click(function() {
						// Must use filter, can't have nasty characters in a selector
						container.find('input[type=checkbox]').filter(function() { return $(this).val() === ev }).remove();
						$(this).remove();
						onChange();
						return false;
					});
					container.find('ul').append(remover);
					remover.show();
					onChange();
				}
				return false;				
			}

			// Activate remover links
      var select = $("#" + id + " select");
      var k;
      var items = $('#' + id + ' ul li');
      for (k = 0; (k < length); k++)
      {
        $(items[k]).data("boxid", values[k]);
        $(items[k]).click(function() { update($(this).data("boxid"), false); return false; });
      }
      
			// Autocomplete theory of operation: quietly add another hidden checkbox 
			// and another remove link for each item added via autocomplete. Reuse them
			// if the item was added previously
			
      var autocompleteText = container.find('.a-autocomplete').find('input[type=text]');
			if (autocompleteText.length)
			{
				autocompleteText.autocomplete({
					source: autocomplete,
					focus: function( event, ui ) {
						autocompleteText.val(ui.item.label);
						return false;
					},
					select: function( event, ui ) {
						autocompleteText.val('');
						// Must use filter, can't have nasty characters in a selector
						if (!container.find('input[type=checkbox]').filter(function() { return $(this).val() === String(ui.item.value) }).length)
						{
							// TODO: this could share more code with the creation of the other checkboxes
							var newBox = $('<input type="checkbox" />');
							newBox[0].style.display = 'none';
							newBox.attr('name', name);
							newBox.val(ui.item.value);
							container.append(newBox);
							var li = $(liHtml(ui.item.label, options));
							li.data("boxid", String(ui.item.value));
			        li.click(function() { update($(this).data("boxid"), false); return false; });
			        container.find('ul').append(li);
						}
						update(false, false, String(ui.item.value));
						return false;
					}
				});
			}
			
      function update(remove, initial, add)
      {
				var value = false;
				if (add !== undefined)
				{
					value = add;
				}
        var ul = $("#" + id + " ul");
				if (!autocomplete)
				{
	        var select = $("#" + id + " select")[0];
	        var index = select.selectedIndex;
				}
				if (!autocomplete)
				{
	        if (index > 0)
	        {
						if ((index === select.length - 1) && options['add'])
						{
							select.selectedIndex = 0;
							$("#" + id + " .add").fadeIn().children('input').focus();
							return;
						}
	          value = select.options[index].value;
	        }
        }

        var boxes = $('#' + id + " input[type=checkbox]");
        
        boxes.each(function()
      	{
      		if ($(this).val() === remove)
      		{
						$(this).attr('checked', false);
      		}
      		else if ($(this).val() === value)
      		{
      			$(this).attr('checked', true);
      		}
      	});
        
        var items = $('#' + id + ' ul li');
        var k;
        var html;

				if (autocomplete)
				{
					// The length is now variable 
					length = items.length;
				}
				
        for (k = 0; (k < length); k++)
        {
          if ($(boxes[k]).is(':checked'))
          {
            $(items[k]).show();
          }
          else
          {
            $(items[k]).hide();
						if (!autocomplete)
						{
		           html += "<option ";
		           if (k == 0)
		           {
		             // First option is "pick one" message
		             html += " selected ";
		           }
		           html += "value=\"" + aHtmlEscape(values[k]) + "\">" +
		             labels[k] + "</option>";
						}
          }
        }
				if (addIndex !== undefined)
				{
					html += "<option value=\"_new\">" + labels[addIndex] + "</option>";
				}
				if (!autocomplete)
				{
	        // Necessary in IE
	        $(select).replaceWith("<select class='a-multiple-select-input' name='select-" + name + "'>" + html + "</select>");
	        $("#" + id + " select").change(function() { update(false, false); });
				}
				if (!initial)
				{
					onChange();
				}
      }
			
			function onChange()
			{
				if (options['onChange'])
				{
					// Receives the outermost element of the enhanced control.
					// To use this to implement autosubmit you might write:
					// onChange: function(multi) { $(multi).parents('form').submit(); }
					var div = $('#' + id);
					options['onChange'](div, div.parents('form'));
				}
			}
      function aHtmlEscape(html)
      {
        html = html.replace('&', '&amp;'); 
        html = html.replace('<', '&lt;'); 
        html = html.replace('>', '&gt;'); 
        html = html.replace('"', '&quot;'); 
        html = html.replace("'", '&#39;'); 
        return html;
      }  
      function liHtml(label, options)
			{
				return '<li class="a-multiple-select-item" style="display: none;"><span>'+label+'</span><a href="#" title="Remove '+label+'" class="a-remove"><span class="icon"></span>'+ options['remove'] + '</a></li>\n';	
			}
			// We need this because you can't have nasty characters in a selector 
			function containsLabel(v)
			{
				var container = $('#' + id);
				if (labels.indexOf(v) !== -1)
				{
					return true;
				}
				var found = false;
				$(container).find('input[type=checkbox]').each(function() {
					if ($(this).val() === v)
					{
						found = true;
					}
				});
				return found;
			}
      update(false, true);
    }
  );
}

// Transforms select elements matching the specified selector.  
// You won't want to do this to every select element in your form,
// so give them a class like .a-radio-select and use a class selector like 
// .aRadioSelect (but not .a-radio-select-container, which we use for
// the span that encloses our toggle buttons). Make sure your selector is 
// specific enough not to match other elements as well.
//
// We set the a-radio-option-selected class on the currently selected link
// element.
//
// If the autoSubmit option is true, changing the selection immediately
// submits the form. There are no guarantees that will work with
// wacky AJAX forms. It works fine with normal forms.
//
// Note the getOption calls that allow the use of custom templates.

function aRadioSelect(target, options)
{
  $(target).each(
    function(i) {
			// Don't do it twice to the same element
			if ($(this).data('a-radio-select-applied'))
			{
				return;
			}
      $(this).hide();
			$(this).data('a-radio-select-applied', 1);
      var html = "";
      var links = "";
      var j;
			var total = this.options.length;
      linkTemplate = getOption("linkTemplate",
        "<a href='#'>_LABEL_</a>");
      spanTemplate = getOption("spanTemplate",
        "<span class='a-radio-select-container'>_LINKS_</span>");
      betweenLinks = getOption("betweenLinks", " ");
      autoSubmit = getOption("autoSubmit", false);
      for (j = 0; (j < this.options.length); j++)
      {
        if (j > 0)
        {
          links += betweenLinks;
        }
        links += 
          linkTemplate.replace("_LABEL_", $(this.options[j]).html());
      }
      span = $(spanTemplate.replace("_LINKS_", links));
      var select = this;
      links = span.find('a');
      $(links[select.selectedIndex]).addClass('a-radio-option-selected');
      links.each(
        function (j)
        {
          $(this).data("aIndex", j);
					$(this).addClass('option-'+j);
					
					if (j == 0)
					{
						$(this).addClass('first');
					}
					
					if (j == total-1)
					{
						$(this).addClass('last');						
					}
          $(this).click(
            function (e)
            {
              select.selectedIndex = $(this).data("aIndex");
              var parent = ($(this).parent());
              parent.find('a').removeClass('a-radio-option-selected'); 
              $(this).addClass('a-radio-option-selected'); 
              if (autoSubmit)
              {
                select.form.submit();
              }
              return false;
            }
          );
        }
      );
      $(this).after(span);
      function getOption(name, def)
      {
        if (name in options)
        {
          return options[name];
        }
        else
        {
          return def;
        }
      }
    }
  );
}

// Simple usage example
// aSelectToList('#a-media-type', {} );

// Usage example where the labels are tags with usage counts, with the
// both alphabetical and popular tag lists present and custom labels:

// aSelectToList('#a-media-tag', 
//   { 
//     tags: true,
//     // MUST contain an anchor tag so our code can bind the click 
//     currentTemplate: "<h5>_LABEL_ <a href='#'><font color='red'><i>x</i></font></a></h5>",
//     popularLabel: "<h4>Popular Tags</h4>",
//     popular: <?php echo aMediaTools::getOption('popular_tags') ?>,
//     alpha: true,
//     // If this contains an 'a' tag it gets turned into a toggle 
//     allLabel: "<h4><a href='#'>All Tags</a></h4>",
//     itemTemplate: "_LABEL_ <span>(_COUNT_)",
//     allVisible: false,
//     all: true
//   });

function aSelectToList(selector, options)
{
  $(selector).each(
    function(i) {
      $(this).hide();
      var total = this.options.length;
      var html = "<ul>";
      var selectElement = this;
      var tags = options['tags'];
      var popular = false;
      var alpha = false;
      var all = true;
      var itemTemplate = options['itemTemplate'];
      if (!itemTemplate)
      {
        if (tags)
        {
          itemTemplate = "_LABEL_ <span>(_COUNT_)";
        }
        else
        {
          itemTemplate = "_LABEL_";
        }
      }
      var currentTemplate;
      if (tags)
      {
        popular = options['popular'];
        all = options['all'];
        alpha = options['alpha'];
      }
      if (options['currentTemplate'])
      {
        currentTemplate = options['currentTemplate'];
      }
      else
      {
        currentTemplate = "<h5>_LABEL_ <a href='#'><font color='red'><i>x</i></font></a></h5>";
      }
      var data = [];
      var re = /^(.*)?\s+\((\d+)\)\s*$/;
      index = -1;
      for (i = 0; (i < total); i++)
      {
        var html = this.options[i].innerHTML;
        if (tags)
        {
          var result = re.exec(html);
          if (result)
          {
            data.push({ 
              label: result[1], 
              count: result[2], 
              value: this.options[i].value
            });
          }
          else
          {
            continue;
          }
        } 
        else
        {
          // Test... carefully... for a non-empty string
          if ((this.options[i].value + '') !== '')
          {
            data.push({
              label: html,
              value: this.options[i].value
            });
          }
          else
          {
            continue;
          }
        }
        if (selectElement.selectedIndex == i)
        {
          // Don't let skipped valueless entries throw off the index
          index = data.length - 1;
        }
      }
      // Make our after() calls in the reverse order so we get
      // the correct final order
      if (all)
      {
        var sorted = data.slice();
        if (alpha)
        {
          sorted = sorted.sort(sortItemsAlpha);
        }
				var lclass = options['listAllClass'];
        var allList = appendList(sorted, lclass);
        if (!options['allVisible'])
        {
          allList.hide();
        }
        if (options['allLabel'])
        {
          var allLabel = $(options['allLabel']);
          if (allLabel)
          {
            var a = allLabel.find('a');
            if (a)
            {
              a.click(function() 
              {
                allList.toggle("slow");
                return false;
              });
            }
          }
          $(selectElement).after(allLabel);
        }
      }
      if (popular)
      {
        var sorted = data.slice();
        sorted = sorted.sort(sortItemsPopular);
        sorted = sorted.slice(0, popular);
        appendList(sorted, options['listPopularClass']);
        if (options['popularLabel'])
        {
          $(selectElement).after($(options['popularLabel']));
        }
      }
      if (index >= 0)
      {
        var current = currentTemplate;
        current = current.replace("_LABEL_", data[index].label);
        current = current.replace("_COUNT_", data[index].count);
        current = $(current);
        var a = current.find('a');
        a.click(function()
        {
          selectElement.selectedIndex = 0;
          $(selectElement.form).submit();
          return false;
        });
        $(selectElement).after(current);
      }
      function appendList(data, c)
      {
        var list = $('<ul></ul>');
        if (c)
        {
          list.addClass(c);
        }
        for (i = 0; (i < data.length); i++)
        {
          var item = itemTemplate;
          if (tags)
          {
            item = item.replace("_COUNT_", data[i].count);
          }
          item = item.replace("_LABEL_", data[i].label);
          var liHtml = "<li><a href='#'>" + item + "</a></li>";
          var li = $(liHtml);
          var a = li.find('a');
          a.data('label', data[i].label);
          a.data('value', data[i].value);
          a.click(function() {
            $(selectElement).val($(this).data('value'));
            $(selectElement.form).submit();
            return false;
          });
          list.append(li);
        }
        $(selectElement).after(list); 
        return list;
      }
    }
  );
  function sortItemsAlpha(a, b)
  {
    x = a.label.toLowerCase();
    y = b.label.toLowerCase();
    // JavaScript has no <> operator 
    return x > y ? 1 :x < y ? -1 : 0;
  }
  function sortItemsPopular(a, b)
  {
    // Most popular should appear first
    return b.count - a.count;
  }
}

// Labeling input elements compactly using their value attribute (i.e. search fields)

// You have an input element. You want it to say 'Search' or a similar label, 
// provided its initial value is empty. On focus, if the label is present, it should clear.
// If they defocus it and it's empty, you want the label to come back. Here you go.

function aInputSelfLabel(selector, label, select, focus, persistentLabel)
{
	var aInput = $(selector);
		
	aInput.each(function() {
		setLabelIfNeeded(this);
		$(this).addClass('a-default-value');
	});
	
	if (focus)
	{	
		aInput.focus();
	};
	
	aInput.focus(function(){
		var v = $(this).val();
		if (v === label) 
		{			
			if (select) 
			{ 		
				aInput.select();
			}
			else
			{
				if (persistentLabel)
				{
					aInput.aSetCursorPosition(0);
				}
				else
				{
					clearLabelIfNeeded(this);							
				};				
			}
		};
	});

	aInput.keydown(function() {
		clearLabelIfNeeded(this);
	});

	aInput.blur(function() {
		setLabelIfNeeded(this);
	});
	
	function setLabelIfNeeded(e)
	{
		var v = $(e).val();
		if (v === '')
		{
			$(e).val(label).addClass('a-default-value');			
		}
	}
	function clearLabelIfNeeded(e)
	{
		var v = $(e).val();
		if (v === label)
		{
			$(e).val('').removeClass('a-default-value');
		}
	}
}

// Got a checkbox and a set of related controls that should only be enabled
// when the checkbox is checked? Here's your answer.

// You can specify four different selectors. pass undefined (not null) to skip a selector.

// When the box is checked, enablesItemsSelector is enabled, and showsItemsSelector is shown.
// When the box is unchecked, enablesItemsSelector is disabled, and showsItemsSelector is hidden.

// For the opposite effect (disable or hide when the box IS checked), use
// disablesItemsSelector and hidesItemsSelector.

// ACHTUNG: don't forget about hidden form elements you might be disabling 
// (Symfony adds them to the last row in a form). Write your selectors carefully,
// check for over-generous selectors when forms seem broken.

// Nesting is permitted. If an outer checkbox would enable a child of an inner checkbox, 
// it first checks a nesting counter to ensure it is not also disabled due to the inner 
// checkbox. This only works if you call aCheckboxEnables for the OUTER checkbox FIRST.
// That is due to the order in which onReady() calls are made by jQuery.

function aCheckboxEnables(boxSelector, enablesItemsSelector, showsItemsSelector, disablesItemsSelector, hidesItemsSelector)
{
	$(boxSelector).data('aCheckboxEnablesSelectors',
		[ enablesItemsSelector, showsItemsSelector, disablesItemsSelector, hidesItemsSelector ]);
	
	$(boxSelector).click(function() 
	{
		update(this);
	});

	function bumpEnabled(selector, show)
	{
		if (selector === undefined)
		{
			return;
		}
		$(selector).each(function() { 
			var counter = $(this).data('aCheckboxEnablesEnableCounter');
			if (counter < 0)
			{
				counter++;
				$(this).data('aCheckboxEnablesEnableCounter', counter);
			}
			if (counter >= 0)
			{
				if (show)
				{
					$(this).show();
				}
				else
				{
					$(this).removeAttr('disabled');
				}
			}
		});
	}

	function bumpDisabled(selector, hide)
	{
		if (selector === undefined)
		{
			return;
		}
		$(selector).each(function() { 
			var counter = $(this).data('aCheckboxEnablesEnableCounter');
			if (counter === undefined)
			{
				counter = 0;
			}	
			counter--;
			$(this).data('aCheckboxEnablesEnableCounter', counter);
			if (hide)
			{
				$(this).hide();
			}
			else
			{
				$(this).attr('disabled', 'disabled');
			}
		});
	}
	
	function update(checkbox)
	{
		var selectors = $(checkbox).data('aCheckboxEnablesSelectors');
		var checked = $(checkbox).attr('checked');
		if (checked)
		{
			bumpEnabled(selectors[0], false);
			bumpEnabled(selectors[1], true);
			bumpDisabled(selectors[2], false);
			bumpDisabled(selectors[3], true);
		}
		else
		{
			bumpDisabled(selectors[0], false);
			bumpDisabled(selectors[1], true);
			bumpEnabled(selectors[2], false);
			bumpEnabled(selectors[3], true);
		}
	}
	// At DOMready so we can affect controls created by js widgets in the form
	$(function() {
		$(boxSelector).each(function() { update(this) });
	});
}

// Similar to the above, but for select options. itemsSelectors is a hash
// of option values pointing to item selectors. On change all of the items
// selectors for the other options get disabled, then the items selector for
// the selected option (if any) gets enabled. Great for enabling a text field
// when "Other" is chosen from an "Institution Type" menu.

// If desired a second hash of option values pointing to item selectors that
// should be shown/hidden rather than enabled/disabled can also be passed.

// Both selector arguments are optional. To skip itemsSelectors, pass undefined (not null)
// for that argument.

function aSelectEnables(selectSelector, itemsSelectors, hideItemsSelectors)
{
	$(selectSelector).data('aSelectEnablesItemsSelectors', itemsSelectors);
	$(selectSelector).data('aSelectEnablesHideItemsSelectors', hideItemsSelectors);
	$(selectSelector).change(function() {
		update(this);
	});

	function update(select)
	{
		var itemsSelectors = $(select).data('aSelectEnablesItemsSelectors');
		var hideItemsSelectors = $(select).data('aSelectEnablesHideItemsSelectors');
		if (itemsSelectors !== undefined)
		{
			for (var option in itemsSelectors)
			{
				$(itemsSelectors[option]).attr('disabled', 'disabled');
			}
			var option = select.value;
			if (itemsSelectors[option])
			{
				$(itemsSelectors[option]).removeAttr('disabled');
			}
		}
		if (hideItemsSelectors !== undefined)
		{
			for (var option in hideItemsSelectors)
			{
				$(hideItemsSelectors[option]).hide();
			}
			var option = select.value;
			if (hideItemsSelectors[option])
			{
				$(hideItemsSelectors[option]).show();
			}
		}
	}
	$(function() {
		$(selectSelector).each(function() { update(this) });
	});
}


function aBusy(selector)
{
	$(selector).each(function() {
		$(this).data('a-busy-html', $(this).html());
		$(this).html("<img src=\"/apostrophePlugin/images/a-icon-loader.gif\"/>");
	});
}

function aReady(selector)
{
	$(selector).each(function() {
		$(this).html($(this).data('a-busy-html'));
	});
}

// Select elements with only one preselected <option> are better presented as static content.
// This gives prettier results with a generic echo $form for things like RSVP forms that
// don't always have more than one possible new state.

// Usage: aSelectToStatic('body') or something less promiscuous

function aSelectToStatic(selector)
{
	$(selector).find('select').each(function() {
		if ((this.options.length == 1) && (this.options[0].selected))
		{
			$(this).after('<span class="a-static-select">' + this.options[0].innerHTML + '</span>');
			$(this).hide();
		}
	});
}

new function($) {
	$.fn.aSetCursorPosition = function(pos) {
		var $this = $(this).get(0);
		if ($this.setSelectionRange) {
			$this.setSelectionRange(pos, pos);
		} else if ($this.createTextRange) {
			var range = $this.createTextRange();
			range.collapse(true);
			range.moveEnd('character', pos);
			range.moveStart('character', pos);
			range.select();
		}
	}
}(jQuery);

new function($) {
	$.fn.aRemoteSubmit = function(update) {
		var rBtn = $(this);
		rBtn.click(function(event){
			event.preventDefault();
			var rForm = rBtn.closest('form');
			var rFormURL = rForm.attr('action');
			$.ajax({
				type: 'POST',
				url: rFormURL,
				dataType: 'html',
				data: rForm.serialize(),
				success: function(data){
					$(update).html(data);
				}
			});
		});
	};
}(jQuery);

new function($)
{
	$.fn.isChildOf = function(b){
		return (this.parents(b).length > 0);
	};		
}(jQuery);
