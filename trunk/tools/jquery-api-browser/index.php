<?php

// is file_get_contents faster? 
ob_start();
include('index.html');
$html = ob_get_contents();
ob_end_clean();

// get the URL request path so we find the right page to display
$request = split('/', preg_replace('/^\/(.*?)(\/|)$/', '$1', $_SERVER['REQUEST_URI']));

// Should this perhaps be cached?
$categories = getcategories('lib/docs/api-docs.xml', $request);

// strip out the AIR loader and replace it with the categories
$html = preg_replace('@<p class="loading"><img src="/assets/images/spinner.gif" /> Loading jQuery API database</p>@', $categories, $html);

echo $html;

function getcategories($filename, $request) {
    $dom= new DOMDocument(); 
    $dom->load($filename); 
    $cats = $dom->getElementsByTagName('cat');

    $html = "<ul id=\"categories\">\n";

    for ($i = 0; $i < $cats->length; $i++) {
        $cat = $cats->item($i);
        $catval = $cat->getAttribute('value');
        $catkey = stripspace($catval);
        
        $selected = $catkey == $request[0] ? ' active' : '';
        
        $html .= "\t" . '<li class="apiheading' . $selected . '"><h2><a id="' . $i . '" href="/' . $catkey . '">' . $catval . "</a></h2>\n";
        $html .= "\t" . '<ul class="subcategories">' . "\n";
    
        $subcats = $cat->getElementsByTagName('subcat');
        for ($j = 0; $j < $subcats->length; $j++) {
            $subcat = $subcats->item($j);
            $subcatval = $subcat->getAttribute('value');
            $subcatkey = stripspace($subcatval);

            list($fn_html, $fn_selected) = getElements($catval, $subcat, $request, 'function');
            list($sel_html, $sel_selected) = getElements($catval, $subcat, $request, 'selector');
            list($prop_html, $prop_selected) = getElements($catval, $subcat, $request, 'property');
            
            $selected = ($subcatkey == $request[1] || $fn_selected || $sel_selected || $prop_selected) ? ' class="active"' : '';
            
            $html .= "\t\t" . '<li id="subcategory' . $j . '"' . $selected . '><a href="/' . $catkey . '/' . $subcatkey . '">' . $subcatval . "</a>\n";
            
            $html .= "\t\t" . '<ul class="functions">' . "\n";
            $html .= $fn_html;
            $html .= $sel_html;
            $html .= $prop_html;
            
            $html .= "\t\t</ul></li>\n";
        }
    
        $html .= "\t</ul></li>\n";
    }

    return $html;    
}

function getElements($catval, $subcat, $request, $tag) {
    $html = '';
    
    $catkey = stripspace($catval);
    
    $element_found = false;
    
    $functions = getOrderedElements($subcat, $tag);
    for ($k = 0; $k < count($functions); $k++) {
        $function = $functions[$k];
        
        $functionval = preg_replace('/^jquery\./i', '$.', $function->getAttribute('name'));

        $params = $function->getElementsByTagName('params');
        $all_params = array();
        for ($l = 0; $l < $params->length; $l++) {
            array_push($all_params, $params->item($l)->getAttribute('name'));
        }
        
        if (count($all_params)) {
            $id = strtolower(trim($functionval) . '_' . join($all_params, '_'));
            $params_str = count($all_params) ? '(' . join($all_params, ', ') . ')' : '';
        } else {
            $id = strtolower(trim($functionval));
            $params_str = '';
        }
        
        $selected = '';
        if ($id == $request[1]) {
            $element_found = true;
            $selected = ' class="active"';
        }
                
        $html .= "\t\t\t" . '<li' . $selected . '"><a href="/' . $catkey . '/' . $id . '">' . $functionval . $params_str . '</a></li>' . "\n";
    }
    
    return array($html, $element_found);
}

function getOrderedElements($context, $tag) {
    $elements = $context->getElementsByTagName($tag);
    $ordered = array();
    
    for ($i = 0; $i < $elements->length; $i++) {
        array_push($ordered, $elements->item($i));
    }
    
    usort($ordered, 'elOrder');
    return $ordered;
}

function elOrder($a, $b) {
    return strcasecmp($a->getAttribute('name'), $b->getAttribute('name'));
}

function stripspace($s) {
    return preg_replace('/\s+/', '_', $s);
}

// source: http://uk2.php.net/manual/en/function.xml-parse.php#87920
function xml2array($url, $get_attributes = 1, $priority = 'tag')
{
    $contents = "";
    if (!function_exists('xml_parser_create'))
    {
        return array ();
    }
    $parser = xml_parser_create('');
    if (!($fp = @ fopen($url, 'rb')))
    {
        return array ();
    }
    while (!feof($fp))
    {
        $contents .= fread($fp, 8192);
    }
    fclose($fp);
    xml_parser_set_option($parser, XML_OPTION_TARGET_ENCODING, "UTF-8");
    xml_parser_set_option($parser, XML_OPTION_CASE_FOLDING, 0);
    xml_parser_set_option($parser, XML_OPTION_SKIP_WHITE, 1);
    xml_parse_into_struct($parser, trim($contents), $xml_values);
    xml_parser_free($parser);
    if (!$xml_values)
        return; //Hmm...
    $xml_array = array ();
    $parents = array ();
    $opened_tags = array ();
    $arr = array ();
    $current = & $xml_array;
    $repeated_tag_index = array (); 
    foreach ($xml_values as $data)
    {
        unset ($attributes, $value);
        extract($data);
        $result = array ();
        $attributes_data = array ();
        if (isset ($value))
        {
            if ($priority == 'tag')
                $result = $value;
            else
                $result['value'] = $value;
        }
        if (isset ($attributes) and $get_attributes)
        {
            foreach ($attributes as $attr => $val)
            {
                if ($priority == 'tag')
                    $attributes_data[$attr] = $val;
                else
                    $result['attr'][$attr] = $val; //Set all the attributes in a array called 'attr'
            }
        }
        if ($type == "open")
        { 
            $parent[$level -1] = & $current;
            if (!is_array($current) or (!in_array($tag, array_keys($current))))
            {
                $current[$tag] = $result;
                if ($attributes_data)
                    $current[$tag . '_attr'] = $attributes_data;
                $repeated_tag_index[$tag . '_' . $level] = 1;
                $current = & $current[$tag];
            }
            else
            {
                if (isset ($current[$tag][0]))
                {
                    $current[$tag][$repeated_tag_index[$tag . '_' . $level]] = $result;
                    $repeated_tag_index[$tag . '_' . $level]++;
                }
                else
                { 
                    $current[$tag] = array (
                        $current[$tag],
                        $result
                    ); 
                    $repeated_tag_index[$tag . '_' . $level] = 2;
                    if (isset ($current[$tag . '_attr']))
                    {
                        $current[$tag]['0_attr'] = $current[$tag . '_attr'];
                        unset ($current[$tag . '_attr']);
                    }
                }
                $last_item_index = $repeated_tag_index[$tag . '_' . $level] - 1;
                $current = & $current[$tag][$last_item_index];
            }
        }
        elseif ($type == "complete")
        {
            if (!isset ($current[$tag]))
            {
                $current[$tag] = $result;
                $repeated_tag_index[$tag . '_' . $level] = 1;
                if ($priority == 'tag' and $attributes_data)
                    $current[$tag . '_attr'] = $attributes_data;
            }
            else
            {
                if (isset ($current[$tag][0]) and is_array($current[$tag]))
                {
                    $current[$tag][$repeated_tag_index[$tag . '_' . $level]] = $result;
                    if ($priority == 'tag' and $get_attributes and $attributes_data)
                    {
                        $current[$tag][$repeated_tag_index[$tag . '_' . $level] . '_attr'] = $attributes_data;
                    }
                    $repeated_tag_index[$tag . '_' . $level]++;
                }
                else
                {
                    $current[$tag] = array (
                        $current[$tag],
                        $result
                    ); 
                    $repeated_tag_index[$tag . '_' . $level] = 1;
                    if ($priority == 'tag' and $get_attributes)
                    {
                        if (isset ($current[$tag . '_attr']))
                        { 
                            $current[$tag]['0_attr'] = $current[$tag . '_attr'];
                            unset ($current[$tag . '_attr']);
                        }
                        if ($attributes_data)
                        {
                            $current[$tag][$repeated_tag_index[$tag . '_' . $level] . '_attr'] = $attributes_data;
                        }
                    }
                    $repeated_tag_index[$tag . '_' . $level]++; //0 and 1 index is already taken
                }
            }
        }
        elseif ($type == 'close')
        {
            $current = & $parent[$level -1];
        }
    }
    return ($xml_array);
}

?>