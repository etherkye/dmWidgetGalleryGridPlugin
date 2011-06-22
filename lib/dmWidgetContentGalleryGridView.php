<?php

class dmWidgetContentGalleryGridView extends dmWidgetPluginView
{
  
  public function configure()
  {
    parent::configure();
    
    $this->addRequiredVar(array('medias', 'method', 'transition'));

    $this->addJavascript(array('dmWidgetGalleryGridPlugin.view', 'lib.colorbox'));
    $this->addStylesheet(array('dmWidgetGalleryGridPlugin.view','lib.colorbox'));
  }

  protected function filterViewVars(array $vars = array())
  {
    $vars = parent::filterViewVars($vars);

    // extract media ids
    $mediaIds = array();
    foreach($vars['medias'] as $index => $mediaConfig)
    {
      $mediaIds[] = $mediaConfig['id'];
    }
    
    // fetch media records
    $mediaRecords = empty($mediaIds) ? array() : $this->getMediaQuery($mediaIds)->fetchRecords()->getData();
    
    // sort records
    $this->mediaPositions = array_flip($mediaIds);
    usort($mediaRecords, array($this, 'sortRecordsCallback'));
    
    // build media tags
    $medias = array();
    $cur_col = 0;
    $cur_row = 1;
    foreach($mediaRecords as $index => $mediaRecord)
    {
      $cur_col ++;
      $mediaTag = $this->getHelper()->media($mediaRecord);

      if (!empty($vars['width']) || !empty($vars['height']))
      {
        // calculate grid images width
        $width = round(dmArray::get($vars, 'width'));
        $height = round(dmArray::get($vars, 'height'));
        $mediaTag->size($width, $height);
      }
  
      $mediaTag->method($vars['method']);
  
      if ($vars['method'] === 'fit')
      {
        $mediaTag->background($vars['background']);
      }
      
      if ($alt = $vars['medias'][$index]['alt'])
      {
        $mediaTag->alt($this->__($alt));
      }
      
      if ($quality = dmArray::get($vars, 'quality'))
      {
        $mediaTag->quality($quality);
      }

      $big = $this->getHelper()->media($mediaRecord);
      if (!empty($vars['big_width'])) {
        if (!empty($vars['big_height'])) {
          $big = $big->size($vars['big_width'].'x'.$vars['big_height']);
        } else {
          $big = $big->size($vars['big_width']);
        }
      }
      
      $medias[] = array(
        'tag'   => $mediaTag,
        'link'  => $vars['medias'][$index]['link'],
        'title' => $vars['medias'][$index]['alt'],
        'src'   => $big->getSrc()
      );
    }

    // check colorbox config options
    if (isset($vars['config']) && !empty($vars['config'])) {
//      if (strpos($vars['config'], '{') !== 0) {
//        $vars['config'] = '['.$vars['config'].']';
//      }
      $vars['config'] = sfYaml::load($vars['config']);
    } else {
      $vars['config'] = array();
    }
  
    // replace media configuration by media tags
    $vars['medias'] = $medias;
    
    return $vars;
  }
  
  protected function sortRecordsCallback(DmMedia $a, DmMedia $b)
  {
    return $this->mediaPositions[$a->get('id')] > $this->mediaPositions[$b->get('id')];
  }
  
  protected function getMediaQuery($mediaIds)
  {
    return dmDb::query('DmMedia m')
    ->leftJoin('m.Folder f')
    ->whereIn('m.id', $mediaIds);
  }

  protected function doRender()
  {
    if ($this->isCachable() && $cache = $this->getCache())
    {
      return $cache;
    }
    
    $vars = $this->getViewVars();
    $helper = $this->getHelper();

    $html = $helper->open('ol.dm_widget_content_gallery_grid.list', array('json' => array_merge(array(
      'transition' => $vars['transition'],
      'speed'     => dmArray::get($vars, 'speed', 350),
      'opacity'     => dmArray::get($vars, 'opacity', .85)
    ), $vars['config'])));

    foreach($vars['medias'] as $media)
    {
      $html .= $helper->open('li.element');
        $html .= $helper->open('div.image');
          $html .= $helper->link(!empty($media['link'])?$media['link']:str_replace(' ','%20',$media['src']))
            ->set(!empty($media['link'])?'':'.gallery_grid_link rel=content_gallery_grid_'.(isset($this->widget['id'])?$this->widget['id']:'images'))
            ->set(array('title' => $media['title']))
            ->text($media['tag']);
        $html .= $helper->close('div');
        $html .= $helper->tag('div.alt',$media['title']);
      $html .= $helper->close('li');
    }
    
    $html .= $helper->close('ol');

    $html .= $helper->tag('br', array('style' => 'clear: both'));
    
    if ($this->isCachable())
    {
      $this->setCache($html);
    }
    
    return $html;
  }
  
  protected function doRenderForIndex()
  {
    $alts = array();
    foreach($this->compiledVars['medias'] as $media)
    {
      if (!empty($media['alt']))
      {
        $alts[] = $media['alt'];
      }
    }
    
    return implode(', ', $alts);
  }  
}