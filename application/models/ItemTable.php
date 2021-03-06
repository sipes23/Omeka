<?php
/**
 * @copyright Roy Rosenzweig Center for History and New Media, 2007-2010
 * @license http://www.gnu.org/licenses/gpl-3.0.txt
 * @package Omeka
 */

/**
 * @package Omeka
 * @subpackage Models
 * @author CHNM
 * @copyright Roy Rosenzweig Center for History and New Media, 2007-2010
 */
class ItemTable extends Omeka_Db_Table
{
    /**
     * Can specify a range of valid Item IDs or an individual ID
     *
     * @param Omeka_Db_Select $select
     * @param string $range Example: 1-4, 75, 89
     * @return void
     */
    public function filterByRange($select, $range)
    {
        // Comma-separated expressions should be treated individually
        $exprs = explode(',', $range);

        // Construct a SQL clause where every entry in this array is linked by 'OR'
        $wheres = array();

        foreach ($exprs as $expr) {
            // If it has a '-' in it, it is a range of item IDs.  Otherwise it is
            // a single item ID
            if (strpos($expr, '-') !== false) {
                list($start, $finish) = explode('-', $expr);

                // Naughty naughty koolaid, no SQL injection for you
                $start  = (int) trim($start);
                $finish = (int) trim($finish);

                $wheres[] = "(items.id BETWEEN $start AND $finish)";

                //It is a single item ID
            } else {
                $id = (int) trim($expr);
                $wheres[] = "(items.id = $id)";
            }
        }

        $where = join(' OR ', $wheres);

        $select->where('('.$where.')');
    }

    /**
     * Run the search filter on the SELECT statement
     *
     * @param Zend_Db_Select
     * @param array
     * @return void
     */
    public function filterBySearch($select, $params)
    {
        //Apply the simple or advanced search
        if (isset($params['search']) || isset($params['advanced'])) {
            $search = new ItemSearch($select, $this->getDb());
            if ($simpleTerms = @$params['search']) {
                $search->simple($simpleTerms);
            }
            if ($advancedTerms = @$params['advanced']) {
                $search->advanced($advancedTerms);
            }
        }
    }

    /**
     * Apply a filter to the items based on whether or not they should be public
     *
     * @param Zend_Db_Select
     * @param boolean Whether or not to retrieve only public items
     * @return void
     */
    public function filterByPublic($select, $isPublic)
    {
        //Force a preview of the public items
        if ($isPublic) {
            $select->where('items.public = 1');
        } else {
            $select->where('items.public = 0');
        }
    }

    public function filterByFeatured($select, $isFeatured)
    {
        //filter items based on featured (only value of 'true' will return featured items)
        if ($isFeatured) {
            $select->where('items.featured = 1');
        } else {
            $select->where('items.featured = 0');
        }
    }

    /**
     * Filter the SELECT statement based on an item's collection
     *
     * @param Zend_Db_Select
     * @param Collection|integer|string Either a Collection object, the collection ID, or the name of the collection
     * @return void
     */
    public function filterByCollection($select, $collection)
    {
        $select->joinInner(array('collections' => $this->getDb()->Collection),
                           'items.collection_id = collections.id',
                           array());

        if ($collection instanceof Collection) {
            $select->where('collections.id = ?', $collection->id);
        } else if (is_numeric($collection)) {
            $select->where('collections.id = ?', $collection);
        } else {
            $select->where('collections.name = ?', $collection);
        }
    }

    /**
     * Filter the SELECT statement based on the item Type
     *
     * @param Zend_Db_Select
     * @param Type|integer|string Type object, Type ID or Type name
     * @return void
     */
    public function filterByItemType($select, $type)
    {
        $select->joinInner(array('item_types' => $this->getDb()->ItemType),
                           'items.item_type_id = item_types.id',
                           array());
        if ($type instanceof Type) {
            $select->where('item_types.id = ?', $type->id);
        } else if (is_numeric($type)) {
            $select->where('item_types.id = ?', $type);
        } else {
            $select->where('item_types.name = ?', $type);
        }
    }

    /**
     * Query must look like the following in order to correctly retrieve items
     * that have all the tags provided (in this example, all items that are
     * tagged both 'foo' and 'bar'):
     *
     *    SELECT i.id
     *    FROM omeka_items i
     *    WHERE
     *    (
     *    i.id IN
     *        (SELECT tg.relation_id as id
     *        FROM omeka_taggings tg
     *        INNER JOIN omeka_tags t ON t.id = tg.tag_id
     *        WHERE t.name = 'foo' AND tg.type = 'Item')
     *    AND i.id IN
     *       (SELECT tg.relation_id as id
     *       FROM omeka_taggings tg
     *       INNER JOIN omeka_tags t ON t.id = tg.tag_id
     *       WHERE t.name = 'bar' AND tg.type = 'Item')
     *    )
     *      ...
     *
     *
     * @param Omeka_Db_Select
     * @param string|array A comma-delimited string or an array of tag names.
     * @return void
     */
    public function filterByTags($select, $tags)
    {
        // Split the tags into an array if they aren't already
        if (!is_array($tags)) {
            $tags = explode(get_option('tag_delimiter'), $tags);
        }

        $db = $this->getDb();

        // For each of the tags, create a SELECT subquery using Omeka_Db_Select.
        // This subquery should only return item IDs, so that the subquery can be
        // appended to the main query by WHERE i.id IN (SUBQUERY).
        foreach ($tags as $tagName) {

            $subSelect = new Omeka_Db_Select;
            $subSelect->from(array('taggings'=>$db->Taggings), array('items.id'=>'taggings.relation_id'))
                ->joinInner(array('tags'=>$db->Tag), 'tags.id = taggings.tag_id', array())
                ->where('tags.name = ? AND taggings.`type` = "Item"', trim($tagName));

            $select->where('items.id IN (' . (string) $subSelect . ')');
        }
    }

    /**
     * Filter the SELECT based on the user who owns the item
     *
     * @param Zend_Db_Select
     * @param integer $userId  ID of the User to filter by
     * @return void
     */
    public function filterByUser($select, $userId, $isUser=true)
    {
        $select->where('items.owner_id = ?', $userId);
    }

    /**
     * Filter SELECT statement based on items that are not tagged with a specific
     * set of tags
     *
     * @param Zend_Db_Select
     * @param array|string Set of tag names (either array or comma-delimited string)
     * @return void
     */
    public function filterByExcludedTags($select, $tags)
    {
        $db = $this->getDb();

        if (!is_array($tags)){
            $tags = explode(get_option('tag_delimiter'), $tags);
        }
        $subSelect = new Omeka_Db_Select;
        $subSelect->from(array('items'=>$db->Item), 'items.id')
                         ->joinInner(array('taggings' => $db->Taggings),
                                     'taggings.relation_id = items.id AND taggings.type = "Item"',
                                     array())
                         ->joinInner(array('tags' => $db->Tag),
                                     'taggings.tag_id = tags.id',
                                     array());

        foreach ($tags as $key => $tag) {
            $subSelect->where('tags.name LIKE ?', $tag);
        }

        $select->where('items.id NOT IN ('.$subSelect->__toString().')');
    }

    /**
     * Filter SELECT statement based on whether items have a derivative image
     * file.
     *
     * @param Zend_Db_Select
     * @param boolean $hasDerivativeImage Whether items should have a derivative
     * image file.
     * @return void
     */
    public function filterByHasDerivativeImage($select, $hasDerivativeImage = true)
    {
        $hasDerivativeImage = $hasDerivativeImage ? '1' : '0';

        $db = $this->getDb();

        $select->joinLeft(array('files'=>"$db->File"), 'files.item_id = items.id', array());
        $select->where('files.has_derivative_image = ?', $hasDerivativeImage);
    }

    /**
     * Possible options: 'public','user','featured','collection','type','tag',
     * 'excludeTags', 'search', 'range', 'advanced', 'hasImage',
     *
     * @param Omeka_Db_Select
     * @param array
     * @return void
     */
    public function applySearchFilters($select, $params)
    {
        foreach ($params as $paramName => $paramValue) {
            if ($paramValue === null || (is_string($paramValue) && trim($paramValue) == '')) {
                continue;
            }

            $boolean = new Omeka_Filter_Boolean;

            switch ($paramName) {
                case 'user':
                    $this->filterByUser($select, $paramValue);
                    break;

                case 'public':
                    $this->filterByPublic($select, $boolean->filter($paramValue));
                    break;

                case 'featured':
                    $this->filterByFeatured($select, $boolean->filter($paramValue));
                    break;

                case 'collection':
                    $this->filterByCollection($select, $paramValue);
                    break;

                case 'type':
                    $this->filterByItemType($select, $paramValue);
                    break;

                case 'tag':
                case 'tags':
                    $this->filterByTags($select, $paramValue);
                    break;

                case 'excludeTags':
                    $this->filterByExcludedTags($select, $paramValue);
                    break;

                case 'hasImage':
                    $this->filterByHasDerivativeImage($select, $boolean->filter($paramValue));
                    break;

                case 'range':
                    $this->filterByRange($select, $paramValue);
                    break;
            }
        }

        $this->filterBySearch($select, $params);

        //If we returning the data itself, we need to group by the item ID
        $select->group('items.id');
    }

    /**
     * Enables sorting based on ElementSet,Element field strings.
     *
     * @param Omeka_Db_Select $select
     * @param string $sortField Field to sort on
     * @param string $sortDir Sorting direction (ASC or DESC)
     */
    public function applySorting($select, $sortField, $sortDir)
    {
        parent::applySorting($select, $sortField, $sortDir);

        $db = $this->getDb();
        $fieldData = explode(',', $sortField);
        if (count($fieldData) == 2) {
            $element = $db->getTable('Element')->findByElementSetNameAndElementName($fieldData[0], $fieldData[1]);
            if ($element) {
                $select->joinLeft(array('et_sort' => $db->ElementText),
                                  "et_sort.record_id = i.id AND et_sort.record_type = 'Item' AND et_sort.element_id = {$element->id}",
                                  array())
                       ->group('items.id')
                       ->order(array("IF(ISNULL(et_sort.text), 1, 0) $sortDir",
                                     "et_sort.text $sortDir"));
            }
        } else {
            if ($sortField == 'random') {
                $select->order('RAND()');
            }
        }
    }

    /**
     * This is a kind of simple factory that spits out proper beginnings
     * of SQL statements when retrieving items
     *
     * @return Omeka_Db_Select
     */
    public function getSelect()
    {
        $select = parent::getSelect();
        $permissions = new PublicPermissions('Items');
        $permissions->apply($select, 'items');

        return $select;
    }

    /**
     * Return the first item accessible to the current user.
     *
     * @return Item|null
     */
    public function findFirst()
    {
        $select = $this->getSelect();
        $select->order('items.id ASC');
        $select->limit(1);
        return $this->fetchObject($select);
    }

    /**
     * Return the last item accessible to the current user.
     *
     * @return Item|null
     */
    public function findLast()
    {
        $select = $this->getSelect();
        $select->order('items.id DESC');
        $select->limit(1);
        return $this->fetchObject($select);
    }

    public function findPrevious($item)
    {
        return $this->findNearby($item, 'previous');
    }

    public function findNext($item)
    {
        return $this->findNearby($item, 'next');
    }

    protected function findNearby($item, $position = 'next')
    {
        //This will only pull the title and id for the item
        $select = $this->getSelect();

        $select->limit(1);

        switch ($position) {
            case 'next':
                $select->where('items.id > ?', (int) $item->id);
                $select->order('items.id ASC');
                break;

            case 'previous':
                $select->where('items.id < ?', (int) $item->id);
                $select->order('items.id DESC');
                break;

            default:
                throw new Omeka_Record_Exception( 'Invalid position provided to ItemTable::findNearby()!' );
                break;
        }

        return $this->fetchObject($select);
    }
}
