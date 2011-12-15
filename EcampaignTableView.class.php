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
  private $tableName ;
  function view($title, $tableName, $views, $fieldPresentations, $filterByFields)
  {
    $this->tableName = $tableName ;

    $viewControl = new EcampaignString();
    $visibleColumnSet = $this->addViewControl($viewControl, $views);
    $viewControl->wrap("span", "class='ecinline'");

    $columnSet = array(); foreach($views as $set)
    $columnSet = array_merge($columnSet, $set);

    $this->header = $header ;

    $filterControls = new EcampaignString();

    $whereClause = $this->addSearchBox($filterControls, $columnSet);
    foreach($filterByFields as $field => $controlType)
    {
      switch ($controlType)
      {
        case 'select' :
          $whereClause .= $this->addSelectFilterControl($filterControls, $columnSet, $field, $fieldPresentations[$field]);
          break ;

        case 'hidden' :
          $whereClause .= $this->addHiddenFilter($field);
          break ;
      }
    }

    $totalRows = $this->getTotalRows($whereClause);

    // note when 'numRows' button pressed, 'numRows' value intentionally overwrtten


    $offset = "0" ; $limit = "10" ;

    $pageControl = new EcampaignString();
    $this->addPageControl($pageControl, $offset, $limit, $totalRows);

//    $pageControl       ->wrap("form", "id='ecampaign-controls' class='ecrow1' ");

    $formatControl = new EcampaignString();
    $this->addFormatControl($formatControl, $totalRows, $whereClause, $offset, $limit);

    $deleteControl = new EcampaignString();
    $this->addDeleteControl($deleteControl, $totalRows, $whereClause, $offset, $limit);

    $form = new EcampaignString();
    $form->add($filterControls)
           ->add($viewControl)
           ->add("<input type='submit' class='button-secondary' name='totalRows' value='Filter'/>")
           ->add("<input type='hidden' name='page' value='ecampaign-log'/>") // hard code url, yuk!
           ->wrap("div");

    $block2 = new EcampaignString();
    $block2->add($pageControl)
           ->add($formatControl)
           ->wrap('div');

    $form->add($block2)
         ->wrap("form")
         ->add($deleteControl)
         ->wrap("div", "id='eclog'");

    if (!isset($_REQUEST['format']))
      $form->add($this->addTableContent($visibleColumnSet, $fieldPresentations, $whereClause, $orderBy = "date", $offset, $limit));
    else
      $form->add($this->writeFile($visibleColumnSet, $whereClause, $orderBy = "date", $offset, $limit));

    $page = new EcampaignString($title);
    $page->wrap("h2")
         ->wrap("a","href='?page=ecampaign-log' style='text-decoration:none' ")
         ->add($form);

 //   $footer = new EcampaignString($wpdb->last_query);  // useful when debugging
 //   $footer->wrap("p")->addTo($page);

    return $page->asHtml();
  }

  function addViewControl($sb, $views)
  {
    $s = new EcampaignString();
    $selected = isset($_GET["view"]) ? $_GET["view"] : __('Normal');
    foreach ($views as $legend => $columnSet)
    {
      $checked = ($legend == $selected) ? "checked='checked'" : '';
      $s->add("<input type='radio' name='view' value='$legend' $checked>$legend</a>");
    }
    $s->addTo($sb);
    return $views[$selected];
  }


  function addHiddenFilter($columnName)
  {
    $filterValue = urldecode($_GET[$columnName]);
    return (empty($filterValue)) ?  "" : " and $columnName = '$filterValue' " ;
  }


  function addSelectFilterControl($sb, $columnSet, $columnName, $presentation)
  {
    global $wpdb ;
    $wildSelection = htmlspecialchars("View all ". $columnSet[$columnName] . "s");

    $dbrows = $wpdb->get_results("SELECT $columnName, count(*) FROM $this->tableName GROUP BY ". $columnName, ARRAY_A);

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

    return $selected == $all ? "" : " and $columnName = '$selected' " ;
  }

  function addSearchBox($sb, $columnSet)
  {
    $search =  urldecode($_GET['search']);
    $sb->add("<label class=search for=s1>search</label><input id=s1 class=search name=search type='text' value='$search' />");    // render search box
    if (empty($search)) return ;
    foreach ($columnSet as $column => $name)
    {
      $where .= " or $column LIKE '%".mysql_real_escape_string($search)."%' ";
    }
    return " and (false $where)" ;
  }

  function addPageControl($scroll, &$offset, &$limit, $totalRows)
  {
    $offset = !empty($_GET['offset']) ? $_GET['offset'] : '0' ;
    $limit = !empty($_GET['pageSize']) ? $_GET['pageSize'] : '20' ;
    $lastPage = intval($totalRows/$limit) + 1 ; // numbering from 1
    if ($offset > $totalRows)             // happens after filtering
      $offset = max(0, $totalRows-$limit); // show last page
    $currentPage = $offset/$limit + 1 ;

    $pageNumbers = new EcampaignString();

    for ($p = 1 ; $p <= $lastPage ; $p++)
    {
      $o = ($p-1) * $limit ;
      if ($p != $currentPage)
      {
        // if last page, force row count on next query in case table has grown
        $tRows = ($p != $lastPage) ? $totalRows : null ;
        $q = $this->createQuery(array("offset" => $o, 'totalRows' => $tRows));
        $pageNumbers->add("<a href='?$q'>$p</a>&nbsp;");
      }
      else
        $pageNumbers->add("<span>$p</span>");
    }

    $pageInput = new EcampaignString();
    $pageInput->add("<label for='offset'>Offset</label>")
              ->add("<input id='offset' type='text' name='offset' size=4  value=$offset />")
              ->add("<label for='pageSize'>Page Size</label>")
              ->add("<input id='pageSize' type='text' name='pageSize' size=2  value=$limit />");

    $scroll->add($pageNumbers->wrap("span", "class='ecinline'"))
           ->add($pageInput->wrap("span", "class='ecinline'"));
  }


  function addFormatControl($sb, $totalRows, $whereClause, $offset, $limit)
  {
    $q1 = $this->createQuery(array("format"=>"CSV","noheader"=>true, "offset"=>0, "pageSize"=>100000));
    $q2 = $this->createQuery(array("format"=>"tab","noheader"=>true, "offset"=>0, "pageSize"=>100000));
    $sb->add(
      "Download <a title='Download $totalRows rows as Comma Separated Values' href='?$q1'>CSV</a>
                <a title='Download $totalRows rows as tab separated values' href='?$q2'>Tabs</a>")
       ->wrap("span", "class='ecinline'");
  }


  function addDeleteControl($sb, $totalRows, $whereClause, $offset, $limit)
  {
    $q = $this->createQuery(array());

    if ($_POST['delete'] == 'yes')
    {
      global $wpdb ;
      $num = $wpdb->query("DELETE FROM $this->tableName WHERE 1=1 ". $whereClause);

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


  function addTableContent($columnSet, $presentations, $where = "" , $orderBy = "date" , $offset = 0,  $limit = 1)
  {
    global $wpdb ;

    $cols = new EcampaignString(array_keys($columnSet));
    $drows = $wpdb->get_var("SET @rownum = 0; ");
    $drows = $wpdb->get_results("SELECT {$cols->toString()} FROM $this->tableName WHERE 1=1 $where ORDER BY $orderBy LIMIT $limit OFFSET $offset", ARRAY_A);

    $thead = new EcampaignString(array_values($columnSet));
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
   * @param $columnSet
   * @param $where
   * @param $orderBy
   * @param $offset
   * @param $limit
   */

  function writeFile($columnSet, $where = "" , $orderBy = "date" , $offset = 0,  $limit = 1)
  {
    global $wpdb ;

    $cols = new EcampaignString(array_keys($columnSet));
    $drows = $wpdb->get_var("SET @rownum = 0; ");
    $drows = $wpdb->get_results("SELECT {$cols->toString()} FROM $this->tableName WHERE 1=1 $where ORDER BY $orderBy LIMIT $limit OFFSET $offset", ARRAY_A);

    $thead = new EcampaignString(array_values($columnSet));
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

  function getTotalRows($whereClause)
  {
    $totalRows = $_GET['totalRows'] ;
    if (!is_numeric($totalRows))
    {
      global $wpdb ;
      $totalRows = $wpdb->get_var("SELECT count(*) FROM $this->tableName WHERE 1=1 ". $whereClause);
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
    return $q ;
  }
}
