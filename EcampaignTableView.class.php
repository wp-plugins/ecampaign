<?php
/**
 * This class displays the ecampaign log in (under admin/tools menu) and supports
 * record filtering and deletion, and paging
 *
 * @author johna
 *
 */


class EcampaignTableView
{
  private $visibleColumnSet, $from, $where, $orderBy = "date" ;
  private $offset, $limit;
  function view($title, $views, $fieldPresentations, $filterByFields)
  {
    $viewControl = new EcampaignString();
    $this->visibleColumnSet = $this->addViewControl($viewControl, $views);
    $viewControl->wrap("span", "class='ecinline'");

    $this->where = $this->visibleColumnSet['_where'];  unset($this->visibleColumnSet['_where']);
    $this->from = $this->visibleColumnSet['_from'];  unset($this->visibleColumnSet['_from']);
    $this->note = $this->visibleColumnSet['_note'];  unset($this->visibleColumnSet['_note']);

    $visibleColumnNames = new EcampaignString(array_keys($this->visibleColumnSet));
    $visibleColumns = $visibleColumnNames->toString();

    $columnSet = array(); foreach($views as $set)
    {
      $columnSet = array_merge($columnSet, $set);
    }

    $this->header = $header ;

    $filterControls = new EcampaignString();

    foreach($filterByFields as $field => $controlType)
    {
      switch ($controlType)
      {
        case 'select' :
          $this->addSelectFilterControl($filterControls, $columnSet, $field, $fieldPresentations[$field]);
          break ;

        case 'hidden' :
          $this->addHiddenFilter($field);
          break ;
      }
    }
    $this->addSearchBox($filterControls, $columnSet);

    $totalRows = $this->getTotalRows();

    // note when 'numRows' button pressed, 'numRows' value intentionally overwrtten

    $pageControl = new EcampaignString();
    $this->addPageControl($pageControl, $totalRows);

//    $pageControl       ->wrap("form", "id='ecampaign-controls' class='ecrow1' ");

    $formatControl = new EcampaignString();
    $this->addFormatControl($formatControl, $totalRows);

    $deleteControl = new EcampaignString();
    $this->addDeleteControl($deleteControl, $totalRows);

    $block1 = new EcampaignString('Filters');
    $block1->add($filterControls)
           ->wrap("div");

    $block2 = new EcampaignString("<label class=ecview>".__("Views")."</label>");
    $block2->add($viewControl)
           ->add($formatControl)
           ->add("<input type='submit' class='button-secondary' name='totalRows' value='Filter'/>")
           ->add("<input type='hidden' name='page' value='ecampaign-log'/>") // hard code url, yuk!
           ->add("&nbsp;<a title='Reset all filters and go to first page' href='?page=ecampaign-log'>Reset</a>")
           ->wrap('div');

    $block3 = new EcampaignString('Pages');
    $block3->add($pageControl)
           ->wrap('div');

    $form = $block1 ;
    $form->add($block2)->add($block3)
         ->wrap("form")
         ->add($deleteControl)
         ->add(isset($this->note) ? $this->note : "")
         ->wrap("div", "id='eclog'");

    global $wpdb ;

    $drows = $wpdb->get_var("SET @rownum = 0; ");
    $drows = $wpdb->get_results(
    "SELECT $visibleColumns
    from $this->from WHERE 1=1 $this->where ORDER BY $this->orderBy
    LIMIT $this->limit OFFSET $this->offset", ARRAY_A);

    if (!isset($_REQUEST['format']))
      $form->add($this->addTableContent($drows, $fieldPresentations));
    else
      $form->add($this->writeFile($drows));

    $page = new EcampaignString($title);
    $page->wrap("h2")
         ->wrap("a","href='?page=ecampaign-log' style='text-decoration:none' ")
         ->add($form);

    if (false)
    {
      $footer = new EcampaignString($wpdb->last_query);  // useful when debugging
      $footer->wrap("p")->addTo($page);
    }
    return $page->asHtml();
  }

  function addViewControl($sb, $views)
  {
    $s = new EcampaignString();
    $selected = isset($_GET["view"]) ? $_GET["view"] : __('Normal');
    $sep = "" ;
    foreach ($views as $legend => $columnSet)
    {
      $checked = ($legend == $selected) ? "checked='checked'" : '';
      $s->add("$sep<input type='radio' name='view' value='$legend' $checked>$legend</a>");
      $sep = " | " ;
    }
    $s->addTo($sb);
    return $views[$selected];
  }


  function addHiddenFilter($columnName)
  {
    $filterValue = urldecode($_GET[$columnName]);
    $this->where .= empty($filterValue) ?  "" : " and $columnName = '$filterValue' " ;
  }


  function addSelectFilterControl($sb, $columnSet, $columnName, $presentation)
  {
    global $wpdb ;
    $wildSelection = htmlspecialchars("View all ". $columnSet[$columnName] . "s");

    $dbrows = $wpdb->get_results("SELECT $columnName, count(*) FROM $this->from GROUP BY ". $columnName, ARRAY_A);

    array_unshift($dbrows, array($columnName=>''));

    $selected = isset($_GET[$columnName]) ? $_GET[$columnName] : '' ;

    $s = new EcampaignString();
    foreach ($dbrows as $dbrow)
    {
      $val = $dbrow[$columnName];
      $label = isset($presentation) ? $presentation->asString($val): $val ;
      $label = $label == '' ? $wildSelection : $label ;
      $att = ($selected == $val) ? " selected='selected' " : "" ;
      $s->add("<option value='$val' $att>$label</option>");
    }
    $s->wrap("select", "name='$columnName'");
    $s->addTo($sb);

    $this->where .= $selected == $all ? "" : " and $columnName = '$selected' " ;
  }

  function addSearchBox($sb)
  {
    $search =  urldecode($_GET['search']);
    $sb->add("<label class=ecsearch for=s1>Search</label><input id=s1 class=ecsearch name=search type='text' value='$search' />");    // render search box
    if (empty($search)) return ;
    foreach ($this->visibleColumnSet as $column => $name)
    {
      $colWords = split("as", $column);
      $where .= " or $colWords[0] LIKE '%".mysql_real_escape_string($search)."%' ";
    }
    $this->where .= " and (false $where)" ;
  }

  function addPageControl($scroll, $totalRows)
  {
    $this->offset = !empty($_GET['offset']) ? $_GET['offset'] : '0' ;
    $this->limit = !empty($_GET['pageSize']) ? $_GET['pageSize'] : '20' ;
    $lastPage = intval(($totalRows-1)/$this->limit) + 1 ; // numbering from 1
    if ($this->offset > $totalRows)              // happens after filtering
      $this->offset = max(0, $totalRows-$this->limit); // show last page
    $currentPage = intval($this->offset/$this->limit) + 1 ;

    $pageNumbers = new EcampaignString();

    for ($p = 1 ; $p <= $lastPage ; )
    {
      $o = ($p-1) * $this->limit ;

      if ($p > 1+1 && $p < $currentPage-3)  // skip link for early pages
      {
        $pageNumbers->add("<span>...</span>");
        $p = $currentPage-3 ;
        continue ;
      }
      if ($p > $currentPage+3 && $p < $lastPage-1)
      {
        $pageNumbers->add("<span>...</span>");  // skip link for later pages
        $p = $lastPage-1 ;
        continue ;
      }

      if ($p != $currentPage)
      {
        // if last page, force row count on next query in case table has grown
        $tRows = ($p != $lastPage) ? $totalRows : null ;
        $q = $this->createQuery(array("offset" => $o, 'totalRows' => $tRows));
        $pageNumbers->add("<a href='?$q'>$p</a>&nbsp;");
      }
      else
        $pageNumbers->add("<span>$p</span>");

      $p++;
    }

    $pageInput = new EcampaignString();
    $pageInput->add("<label for='offset'>Offset</label>")
              ->add("<input id='offset' type='text' name='offset' size=4' value='$this->offset' />")
              ->add("<label for='pageSize'>Page Size</label>")
              ->add("<input id='pageSize' type='text' name='pageSize' size='2'  value='$this->limit' />");

    $scroll->add($pageNumbers->wrap("span", "class='ecinline'"))
           ->add($pageInput->wrap("span", "class='ecinline'"));
  }


  function addFormatControl($sb, $totalRows)
  {
    $q1 = $this->createQuery(array("format"=>"CSV","noheader"=>true, "offset"=>0, "pageSize"=>100000));
    $q2 = $this->createQuery(array("format"=>"tab","noheader"=>true, "offset"=>0, "pageSize"=>100000));
    $sb->add(
      "Download <a title='Download $totalRows rows as Comma Separated Values' href='?$q1'>CSV</a>
                <a title='Download $totalRows rows as tab separated values' href='?$q2'>Tabs</a>")
       ->wrap("span", "class='ecinline'");
  }


  function addDeleteControl($sb, $totalRows)
  {
    $tables = split(" ", $this->from);  // cannot delete rows selected in a multi table join
    if (count($tables) > 1) return ;

    $q = $this->createQuery(array());

    if ($_POST['delete'] == 'yes')
    {
      global $wpdb ;
      $num = $wpdb->query("DELETE FROM $this->from WHERE 1=1 ". $this->where);

      $message = "<div class='ecrow1 ecstatus'>$num rows deleted.</div>";
    }
    else
    {
      $message = "" ;
    }

    $deleteInfo = __("delete the $totalRows rows in this page and other pages. This is not reversible.");

    $script = "
      if (!confirm('$deleteInfo'))
        return false;
      jQuery(this).closest('form').get().submit();
      return false ;";

    $deleteControl = new EcampaignString();
    $deleteControl->add("<input type='hidden' name='page' value='ecampaign-log' />")
                  ->add("<input type='hidden' name='delete' value='yes' />")
                  ->add("<input type='submit' title='$deleteInfo' onclick=\"$script\"
                    name='totalRows' value='delete' class='button-secondary' />")
                  ->add("<span class='ecstatus'></span>")
                  ->add("<span style='color:grey; float:right'>c1 is checkbox 1&nbsp;&nbsp;c2 is checkbox 2</span>")
                  ->wrap("form", "method='post' action='?$q' id='ecampaign-delete-form'")
                  ->addTo($sb);

    $sb->add($message);
  }


  function addTableContent($drows, $presentations)
  {
    $thead = new EcampaignString(array_values($this->visibleColumnSet));
    $trows = new EcampaignString();
    $tcols = new EcampaignString();

    // this is the normal HTML table output

    $thead->wrapAll('th')->wrap('tr')->addTo($trows);

    $tcols = new EcampaignString();
    foreach ($drows as $dcols)
    {
      foreach($dcols as $dkey=>$dval)
      {
        if (isset($presentations[$dkey]))
          $dcols[$dkey] = $presentations[$dkey]->inTable($dval);
      }
      $tcols->set($dcols)->wrapAll("td")->wrap("tr")->addTo($trows);
    }
    return $trows->wrap('table', " class='wp-list-table widefat' ")->asHtml();
  }

  /**
   * write raw downloadable formats CSV and tab separate
   *
   * @param $drow results from db
   */

  function writeFile($drows)
  {
    $thead = new EcampaignString(array_values($this->visibleColumnSet));
    $trows = new EcampaignString();
    $tcols = new EcampaignString();

    // handle raw downloadable formats CSV and tab separate
    switch (strtolower($_REQUEST['format']))
    {
      case 'tab' : $glue = "\t" ; break ;
      case 'csv' : $glue = "," ; break ;
    }
    $filename = 'ecampaign-' . date('ymd') . '.txt' ;
    if (isset($_REQUEST['noheader']))
    {
      header( "Content-Type: text/plain" );
      header( "Content-Disposition: attachment; filename=$filename" );
    }

    $thead->implode($glue)->addTo($trows);

    foreach ($drows as $dcols)
    {
      foreach($dcols as $dkey=>$dval)
      {
        switch($dkey)
        {
          case 'address':
          case 'info':
            $dcols[$dkey] = '"' . $dval . '"';
          break;
        }
      }
      $tcols->set($dcols)->implode($glue)->addTo($trows);
    }
    echo $trows->asHtml();    // not HTML just a string
    exit(0);
  }

  function getTotalRows()
  {
    $totalRows = $_GET['totalRows'] ;
    if (!is_numeric($totalRows))
    {
      global $wpdb ;
      $totalRows = $wpdb->get_var("SELECT count(*) FROM $this->from WHERE 1=1 ". $this->where);
    }
    return $totalRows ;
  }

  /**
   * update the query string with the new key pairs supplied in $params
   * (there must be a better way of doing this)
   * key pairs will be removed from existing string if params value is set to null.
   */
  static function createQuery($params)
  {
    $q = $_SERVER['QUERY_STRING'];    // does this work on all servers ?
    // if the 'filter' appears in GET string, we have to count number of
    // records which is only necesseary on the first filter. Subsequent
    // the recordcount is kept carried forward

    foreach($params as $key => $value)
    {
      $count = 0 ;
      $keyPair = empty($value) ? "" : "&$key=". urlencode($value) ;
      $q = preg_replace("/&$key=[^&]*/", $keyPair, $q, -1, $count);
      if ($count == 0 && !empty($keyPair))
       $q .= "$keyPair" ;
    }
    return self::trimQuery($q);
  }

  /**
   * remove all the arguments that empty values (to reduce url lengths)
   * @param unknown_type $q
   */
  static function trimQuery($q)
  {
    $q1 = preg_replace("/[^&]+=(?:&|$)/", "", $q);
    return $q1 ;
  }
}
