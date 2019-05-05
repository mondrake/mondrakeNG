<?php

namespace mondrakeNG\mm\classes;

use mondrakeNG\mm\core\MMObj;

class AXDoc extends MMObj
{

    public function create($clientPKMap = false)
    {
        $res = parent::create($clientPKMap);
        if ($res == 1 and count($this->docItems) > 0) {
            foreach ($this->docItems as $itm) {
                $itm->doc_id = $this->doc_id;
                $itm->create($clientPKMap);
            }
        }
        if ($this->doc_type_id == 4) {
            $pf = new AXPortfolio;
            $pf->dayValuation(1, $this->doc_id);
        }
        return $res;
    }

    public function read($docId, $loadItems = true)
    {
        $res = parent::read($docId);
        if ($loadItems) {
            $docItem = new AXDocItem;
            $this->docItems = $docItem->getDocItems($docId);
        }
        return $this;
    }

    public function delete($clientPKMap = false)
    {
        if (count($this->docItems) > 0) {
            foreach ($this->docItems as $itm) {
                $itm->delete($clientPKMap);
            }
        }
        $res = parent::delete($clientPKMap);
        return $res;
    }

    public function loadFromArray($arr, $clientPKReplace = false)
    {
        parent::loadFromArray($arr, $clientPKReplace);
        // doc items
        $this->docItems = [];
        if (count($arr[docItems] > 0)) {
            foreach ($arr[docItems] as $itm) {
                $docItem = new AXDocItem;
                $docItem->loadFromArray($itm, $this->doc_id, $clientPKReplace);
                $this->docItems[] = $docItem;
            }
        }
    }

    public function synch($src, $clientPKMap = false)
    {
        parent::synch($src, $clientPKMap);
        $res = $this->update();

        // deletes docItems in target not existing in src
        foreach ($this->docItems as $itm) {
            $toBeDeleted = true;
            foreach ($src->docItems as $srcitm) {
                if ($itm->doc_item_id == $srcitm->doc_item_id) {
                    $toBeDeleted = false;
                    break;
                }
            }
            if ($toBeDeleted) {
                $itm->delete($clientPKMap);
            }
        }
        // synchs existing src docItems with target
        foreach ($src->docItems as $srcitm) {
            foreach ($this->docItems as $itm) {
                if ($itm->doc_item_id == $srcitm->doc_item_id) {
                    $itm->synch($srcitm, $clientPKMap);
                    //$itm->update();
                    $srcitm->_synched = true;
                    break;
                }
            }
        }
        // adds src docItems not existing in target
        foreach ($src->docItems as $srcitm) {
            if (!$srcitm->_synched) {
                $srcitm->create($clientPKMap);
                $this->docItems[] = clone $srcitm;
            }
        }

        if ($this->doc_type_id == 4) {
            $pf = new AXPortfolio;
            $pf->dayValuation(1, $this->doc_id);
        }

        return 1;
    }
}
