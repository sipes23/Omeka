<?php 
/**
 * @copyright Roy Rosenzweig Center for History and New Media, 2009
 * @license http://www.gnu.org/licenses/gpl-3.0.txt
 * @package Omeka
 * @access private
 */
 
/**
 * Adds default behavior associated with the 'public' and 'featured' flags.
 *
 * @package Omeka
 * @subpackage Mixins
 * @copyright Roy Rosenzweig Center for History and New Media, 2009
 */
class Mixin_PublicFeatured extends Omeka_Record_Mixin
{
    private $_wasPublic;
    private $_wasFeatured;
    
    /**
     * @return boolean
     */
    public function isPublic()
    {
        return (boolean)$this->_record->public;
    }
    
    /**
     * @see Item::afterSave()
     * @param boolean
     * @return void
     */
    public function setPublic($flag)
    {
        $this->_wasPublic = $this->isPublic();
        $filter = new Omeka_Filter_Boolean;
        $this->_record->public = $filter->filter($flag);
    }
    
    public function isFeatured()
    {
        return (boolean)$this->_record->featured;
    }
    
    public function setFeatured($flag)
    {
        $this->_wasFeatured = $this->isFeatured();
        $filter = new Omeka_Filter_Boolean;
        $this->_record->featured = $filter->filter($flag);
    }
    
    /**
     * Retrieve formatted hooks like 'make_item_public', 'make_collection_not_featured', etc.
     * 
     * @param string Currently, 'public' or 'featured'
     * @param boolean
     * @return string
     */
    protected function getHookName($state, $flag)
    {
        // e.g., 'item'
        $modelNameForHook = strtolower(get_class($this->_record));
        $action = ($flag ? '' : 'not_') . $state;
        return "make_{$modelNameForHook}_{$action}";
    }

    public function beforeSave()
    {
        $this->setPublic($this->_record->public);
        $this->setFeatured($this->_record->featured);
    }
    
    public function afterSave()
    {
        if ($this->isPublic() and !$this->_wasPublic) {
            $hookName = $this->getHookName('public', true);
        } else if (!$this->isPublic() and $this->_wasPublic) {
            $hookName = $this->getHookName('public', false);
        }
        
        if ($this->isFeatured() and !$this->_wasFeatured) {
            $hookName = $this->getHookName('featured', true);
        } else if (!$this->isFeatured() and $this->_wasFeatured) {
            $hookName = $this->getHookName('featured', false);
        }

        if (isset($hookName)) {
            fire_plugin_hook($hookName, $this->_record);
        }
    }
}
