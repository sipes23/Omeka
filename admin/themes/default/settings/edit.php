<?php head(array('title'=>'Edit Settings', 'content_class' => 'vertical-nav', 'bodyclass'=>'settings primary')); ?>
<script type="text/javascript" charset="utf-8">
    Event.observe(window, 'load', function(){
        var testButton = new Element('button', {'type': 'button', 'id': 'test-button'});
        var loaderGif = new Element('img', {'src': '<?php echo img("loader.gif"); ?>'});
        var resultDiv = new Element('div', {'id': 'im-result'});
        var imageMagickInput = $('path_to_convert');
        imageMagickInput.insert({'after':resultDiv});
        imageMagickInput.insert({'after': testButton});
        testButton.update('Test').observe('click', function(e){
            testButton.insert({'after': loaderGif});
            new Ajax.Request('<?php echo uri(array("controller"=>"settings","action"=>"check-imagemagick")); ?>', {
                method: 'get',
                parameters: 'path-to-convert=' + imageMagickInput.getValue(),
                onComplete: function(t) {
                    loaderGif.hide();
                    resultDiv.update(t.responseText);
                }
            });
        });
    });
</script>

<h1>General Settings</h1>

<?php common('settings-nav'); ?>

<div id="primary">
<?php echo flash(); ?>
<?php echo $this->form; ?>
</form>
</div>
<?php foot(); ?>