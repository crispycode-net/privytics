<script src="../wp-content/plugins/privytics/includes/moment.min.js"></script>

<link rel="stylesheet" href="../wp-content/plugins/privytics/includes/vanilla-datetimerange-picker.css" />
<script src="../wp-content/plugins/privytics/includes/vanilla-datetimerange-picker.js"></script>

<script src='../wp-content/plugins/privytics/includes/d3.min.js'></script>
<script src="../wp-content/plugins/privytics/includes/d3-sankey.min.js"></script>

<link rel="stylesheet" href="../wp-content/plugins/privytics/includes/privytics.css" />

<?php

// Function to query the wordpress database and return the results
function get_data()
{
  // initialize php DateTime() object $startDate with the beginning of today and $endDate with the end of today
  $startDate = new DateTime('today', new DateTimeZone('Europe/Berlin'));
  $startDate->setTime(0, 0, 0);
  $endDate = new DateTime('today', new DateTimeZone('Europe/Berlin'));
  $endDate->setTime(23, 59, 59);

  // get the unix timestamp query parameters start and end from the url and convert them to php date objects if they are set
  if (isset($_GET['start']) && isset($_GET['end'])) {
    $startDate = date('Y-m-d H:i:s', $_GET['start']);
    $endDate = date('Y-m-d H:i:s', $_GET['end']);

    // convert startDate and endDate to local time
    $startDate = new DateTime($startDate, new DateTimeZone('UTC'));
    $startDate->setTimezone(new DateTimeZone('Europe/Berlin'));

    $endDate = new DateTime($endDate, new DateTimeZone('UTC'));
    $endDate->setTimezone(new DateTimeZone('Europe/Berlin'));
  }

  global $wpdb;
  $table_name_session = $wpdb->prefix . 'privytics_session_processed';
  $table_name_action = $wpdb->prefix . 'privytics_action_processed';

  $startDateFormatted = $startDate->format('Y-m-d H:i:s');
  $endDateFormatted = $endDate->format('Y-m-d H:i:s');

  // Optional step 0 to get the referrer
  $referrerSql = "";
  $referrerChecked = isset($_GET['showReferrer']) && $_GET['showReferrer'] == 'true' ? true : false;
  if ($referrerChecked) {
    $referrerSql = "SELECT 0 AS STEP, CASE WHEN s.referrer = '' OR s.referrer IS NULL THEN 'unknown' ELSE s.referrer END AS source_id, CASE WHEN s.referrer = '' OR s.referrer IS NULL THEN 'unknown' ELSE s.referrer END AS source_name, CONCAT('1. ', pageid) AS target_id, pageid AS target_name, COUNT(1) AS ANZ
      FROM $table_name_action a 
      LEFT JOIN $table_name_session s ON s.id = a.sessionid
      WHERE a.asequence = 1
      AND a.atime BETWEEN '$startDateFormatted' AND '$endDateFormatted'
      GROUP BY s.referrer, a.pageid
    
      UNION
      
      ";
  }

  $sql = "$referrerSql 
    SELECT 1 AS STEP, CONCAT('1. ', pageid) as source_id, pageid as source_name, 'DROP' AS target_id, 'DROP' AS target_name, COUNT(1) AS ANZ
    FROM $table_name_action a 
    WHERE a.asequence = 1
    AND a.atime BETWEEN '$startDateFormatted' AND '$endDateFormatted'
    AND NOT EXISTS (SELECT 1 FROM $table_name_action a_next WHERE a_next.sessionid = a.sessionid AND a_next.asequence > 1)
    GROUP BY pageid
    
    UNION
    
    SELECT 2 AS STEP, CONCAT('1. ', prevpageid) AS source_id, prevpageid AS source_name, CONCAT('2. ', pageid) AS target_id, pageid AS target_name, COUNT(1) AS ANZ
    FROM $table_name_action a 
    WHERE a.asequence = 2
    AND a.atime BETWEEN '$startDateFormatted' AND '$endDateFormatted'
    GROUP BY prevpageid, pageid
    
    UNION
    
    SELECT 3 AS STEP, CONCAT('2. ', prevpageid) AS source_id, prevpageid AS source_name, CONCAT('3. ', pageid) AS target_id, pageid AS target_name, COUNT(1) AS ANZ
    FROM $table_name_action a 
    WHERE a.asequence = 3
    AND a.atime BETWEEN '$startDateFormatted' AND '$endDateFormatted'
    GROUP BY prevpageid, pageid
    
    UNION
    
    SELECT 4 AS STEP, CONCAT('3. ', prevpageid) AS source_id, prevpageid AS source_name, CONCAT('4. ', pageid) AS target_id, pageid AS target_name, COUNT(1) AS ANZ
    FROM $table_name_action a 
    WHERE a.asequence = 4
    AND a.atime BETWEEN '$startDateFormatted' AND '$endDateFormatted'
    GROUP BY prevpageid, pageid";

  $results = $wpdb->get_results($sql, ARRAY_A);

  // get the unique nodes from the results by looking into the source_id and target_id column values
  $nodes = array();
  foreach ($results as $row) {
    $source_id = $row['source_id'];
    $target_id = $row['target_id'];
    $source_name = $row['source_name'];
    $target_name = $row['target_name'];

    $nodes[$source_id] = array('id' => $source_id, 'name' => $source_name);
    $nodes[$target_id] = array('id' => $target_id, 'name' => $target_name);
  }

  // get the unique links from the results by looking into the source_id and target_id column values and use the ANZ column as the value
  $links = array();
  foreach ($results as $row) {
    $source_id = $row['source_id'];
    $target_id = $row['target_id'];
    $value = $row['ANZ'];
    $step = $row['STEP'];

    $links[] = array('source' => $source_id, 'target' => $target_id, 'value' => $value, 'step' => $step);
  }

  // create a json object with the data
  $data = array('nodes' => array_values($nodes), 'links' => $links);

  // return the json object
  return json_encode($data);
}

function getAveragePathDepthData() {
  // initialize php DateTime() object $startDate with the beginning of today and $endDate with the end of today
  $startDate = new DateTime('today', new DateTimeZone('Europe/Berlin'));
  $startDate->setTime(0, 0, 0);
  $endDate = new DateTime('today', new DateTimeZone('Europe/Berlin'));
  $endDate->setTime(23, 59, 59);

  // get the unix timestamp query parameters start and end from the url and convert them to php date objects if they are set
  if (isset($_GET['start']) && isset($_GET['end'])) {
    $startDate = date('Y-m-d H:i:s', $_GET['start']);
    $endDate = date('Y-m-d H:i:s', $_GET['end']);

    // convert startDate and endDate to local time
    $startDate = new DateTime($startDate, new DateTimeZone('UTC'));
    $startDate->setTimezone(new DateTimeZone('Europe/Berlin'));

    $endDate = new DateTime($endDate, new DateTimeZone('UTC'));
    $endDate->setTimezone(new DateTimeZone('Europe/Berlin'));
  }

  global $wpdb;
  $table_name_action = $wpdb->prefix . 'privytics_action_processed';

  $startDateFormatted = $startDate->format('Y-m-d H:i:s');
  $endDateFormatted = $endDate->format('Y-m-d H:i:s');

  // select average max path depth per session for each page in the given time range
  $sql = "SELECT ss.pageid, AVG(s.sessionDepth) AS avg_max_path_depth
    FROM (
      SELECT sessionid, MAX(asequence) AS sessionDepth
      FROM $table_name_action
      WHERE atime BETWEEN '$startDateFormatted' AND '$endDateFormatted'
      GROUP BY sessionid
    ) s
    INNER JOIN $table_name_action ss ON ss.sessionid = s.sessionid AND ss.asequence = 1
    WHERE ss.atime BETWEEN '$startDateFormatted' AND '$endDateFormatted'
    GROUP BY ss.pageid";
  
  // execute the sql query
  $results = $wpdb->get_results($sql, ARRAY_A);
  // output the results as json
  return json_encode($results);
}

?>




<script>
  function update(newStart, newEnd) {

    var startDate = newStart;
    var endDate = newEnd;

    if (!newStart || !newEnd) {
      // get the start and end date from the date time picker
      const start = moment(document.getElementById('datetimerange-input1').value.split(' - ')[0], 'DD.MM.YYYY HH:mm');
      const end = moment(document.getElementById('datetimerange-input1').value.split(' - ')[1], 'DD.MM.YYYY HH:mm');

      // convert the start and end date to unix timestamps
      startDate = start.unix();
      endDate = end.unix();
    }

    // get value from showReferrer checkbox
    const showReferrer = document.getElementById('showReferrer').checked;

    // reload the page with the new start and end date
    window.location.href = `?page=privytics_sankey&start=${startDate}&end=${endDate}&showReferrer=${showReferrer}`;
  }
</script>

<div style="margin: 12px 12px 12px 0px;">

  <div class="priv-card priv-card-2" style="align-items: center;">
    <h2 style="margin: 0px 22px 0px 0px;">User Flow</h2>
    <input type="text" id="datetimerange-input1" style="width:250px;" />
    <?php
    $referrerChecked = isset($_GET['showReferrer']) && $_GET['showReferrer'] == 'true' ? true : false;
    if ($referrerChecked) {
      echo '<input type="checkbox" id="showReferrer" style="margin-left: 12px;" checked="checked">';
    } else {
      echo '<input type="checkbox" id="showReferrer" style="margin-left: 12px;">';
    }
    ?>
    <label for="showReferrer">Start with Referrer</label>

    <button id="updateButton" type="button" style="margin-left: 12px;" onclick="update()">Update</button>
  </div>

  <svg id="canvas" class="priv-card priv-card-2" style="width: 100%; margin-top: 10px; padding: 0px;"></svg>
    
  <div id="avgPathData" class="priv-card priv-card-2" style="width: 100%; margin-top: 12px; padding: 0px; display: flex; flex-direction: column; align-items: flex-start; ">
    <h2 style="margin: 8px 0px 8px 12px;">Average Session Depth per Start Page</h2>
  </div>

  <script>
    // get the query parameters start and end from the url and convert them to moment js objects
    const urlParams = new URLSearchParams(window.location.search);
    const startParam = urlParams.get('start');
    const endParam = urlParams.get('end');
    let startDate = moment().startOf('day');
    let endDate = moment().endOf('day');
    if (startParam && endParam) {
      // convert the unix timestamps in startParam and endParam to moment js objects
      startDate = moment.unix(startParam);
      endDate = moment.unix(endParam);
    }

    // https://www.cssscript.com/date-range-picker-predefined-ranges/
    const datePicker = new DateRangePicker('datetimerange-input1', {
      startDate: startDate,
      endDate: endDate,
      timePicker24Hour: true,
      locale: {
        direction: 'ltr',
        format: 'DD.MM.YYYY HH:mm',
        separator: ' - ',
        applyLabel: 'Apply',
        cancelLabel: 'Cancel',
        weekLabel: 'W',
        customRangeLabel: 'Custom Range',
        daysOfWeek: moment.weekdaysMin(),
        monthNames: moment.monthsShort(),

        firstDay: moment.localeData().firstDayOfWeek()
      },
    }, function(start, end) {
      // get the UTC time from moment js start and end and then convert to unit timestamp
      const startDate = start.unix();
      const endDate = end.unix();

      // // get value from showReferrer checkbox
      // const showReferrer = document.getElementById('showReferrer').checked;

      // // reload the page with the new start and end date
      // window.location.href = `?page=privytics_sankey&start=${startDate}&end=${endDate}&showReferrer=${showReferrer}`;

      update(startDate, endDate);
    })

    // get the data from the php function passing start and end date    

    const data = <?php echo get_data(); ?>;

    createChart(data);

    function createChart(data) {
      // sort grapg.links objects so that the links with target DROP are at the end
      data.links.sort((a, b) => {
        if (a.target === "DROP") {
          return 1;
        } else if (b.target === "DROP") {
          return -1;
        } else {
          // sort by value
          return b.value - a.value;
        }
      });

      var width = document.getElementById('canvas').clientWidth;
      const margin = 10;
      const height = 500;
      const svgBackground = "#ffffff";
      const nodeWidth = 12;
      const nodePadding = 16;
      const nodeOpacity = 0.8;
      const linkOpacity = 0.5;
      const nodeDarkenFactor = 0.3;
      const nodeStrokeWidth = 4;
      const arrow = "\u2192";
      const nodeAlignment = d3.sankeyCenter;
      const colorScale = d3.interpolateRainbow;
      const path = d3.sankeyLinkHorizontal();

      function addGradientStop(gradients, offset, fn) {
        return gradients.append("stop")
          .attr("offset", offset)
          .attr("stop-color", fn);
      }

function color(index) {
  let ratio = index / (data.nodes.length - 1.0);
  return colorScale(ratio);
}

function color2(index, length) {
  let ratio = index / (length - 1.0);
  return colorScale(ratio);
}

      function darkenColor(color, factor) {
        return d3.color(color).darker(factor)
      }

      function getGradientId(d) {
        var ignorandId = `gradient_${d.source.id}_${d.target.id}`;
        // replace all spaces and dots and braces in the ignorandId
        return ignorandId.replace(/[\s\.\(\)]/g, "_");
      }

      // count from 0 to 29 and print out the result for the color() function for each index
      for (let i = 0; i < 10; i++) {
        console.log(`${color2(i, 10)}`);
      }

      const svg = d3.select("#canvas")
        .attr("width", width)
        .attr("height", height)
        .style("background-color", svgBackground)
        .append("g")
        .attr("transform", `translate(${margin},${margin})`);

      if (data.nodes.length == 0)
        return;

      // Define our sankey instance.
      const graphSize = [width - 2 * margin, height - 2 * margin];
      const sankey = d3.sankey()
        .size(graphSize)
        .linkSort(null)
        .nodeId(d => d.id)
        .nodeWidth(nodeWidth)
        .nodePadding(nodePadding)
        .nodeAlign(nodeAlignment);
      let graph = sankey(data);

      // Loop through the nodes. Set additional properties to make a few things
      // easier to deal with later.
      graph.nodes.forEach(node => {
        if (node.id != "DROP") {
          let fillColor = color(node.index);
          node.fillColor = fillColor;
          node.strokeColor = darkenColor(fillColor, nodeDarkenFactor);
          node.width = node.x1 - node.x0;

          let regularHeight = node.y1 - node.y0;
          let oneHeightValue = regularHeight / node.value;

          node.height = node.y1 - node.y0;
        }
      });

      // Build the links.
      let svgLinks = svg.append("g")
        .classed("links", true)
        .selectAll("g")
        .data(graph.links)
        .enter()
        .append("g");
      let gradients = svgLinks.append("linearGradient")
        .attr("gradientUnits", "userSpaceOnUse")
        .attr("x1", d => d.source.x1)
        .attr("x2", d => d.target.x0)
        .attr("id", d => getGradientId(d));
      addGradientStop(gradients, 0.0, d => color(d.source.index));
      addGradientStop(gradients, 1.0, d => color(d.target.index));
      svgLinks.append("path")
        .classed("link", true)
        .attr("d", path)
        .attr("fill", "none")
        .attr("stroke", d => `url(#${getGradientId(d)})`)
        .attr("stroke-width", d => Math.max(1.0, d.width))
        .attr("stroke-opacity", d => {
          if (d.target.id == "DROP")
            return 0.0;
          else
            return linkOpacity;
        });

      // Add hover effect to links where the target is not DROP
      svgLinks.append("title")
        .filter(d => d.target.id != "DROP")
        .text(d => `${d.source.name} ${arrow} ${d.target.name}\n${d.value}`);
      

      let svgNodes = svg.append("g")
        .classed("nodes", true)
        .selectAll("rect")
        .data(graph.nodes)
        .enter()
        .append('g');

      svgNodes.append("rect")
        .classed("node", true)
        .attr("x", d => d.x0)
        .attr("y", d => d.y0)
        .attr("width", d => d.width)
        .attr("height", d => d.height)
        .attr("fill", d => d.fillColor)
        .attr("opacity", nodeOpacity)
        .attr("stroke", d => d.strokeColor)
        .attr("stroke-width", 0);

      svgNodes
        .filter(d => d.height > 0)
        .append("text")
        .attr("x", d => d.x0 - 2)
        .attr("y", d => {
          return d.y0 + d.height / 2;
        })
        .attr("dy", ".35em")
        .attr("text-anchor", "end")
        .text(d => `${d.name}: ${d.value} view(s)`)
        .filter(d => d.x0 < 10)
        .attr("x", d => d.x0 + d.width + 2)
        .attr("text-anchor", "start");

      // Add hover effect to nodes.
      svgNodes.append("title")
        .text(d => `${d.name}\n${d.value} view(s)`);
    }

    // get the average path data
    const avgPathData = <?php echo getAveragePathDepthData(); ?>;
    // sort the data by the average path depth
    avgPathData.sort((a, b) => b.avg_max_path_depth - a.avg_max_path_depth);
    // create a table inside the avgPathData div
    const avgPathTable = d3.select("#avgPathData")    
      .append("table")
      .attr("class", "avg");
    // create a row with two tds for each path
    const avgPathRows = avgPathTable.selectAll("tr")
      .data(avgPathData)      
      .enter()
      .append("tr");
    // add the path name to the first td
    avgPathRows.append("td")
      .text(d => `${d.pageid}:`)
      .attr("class", "avgPathDepthHeader avgCell");
    // add the average depth to the second td
    avgPathRows.append("td")
      .text(d => `${parseFloat(d.avg_max_path_depth).toFixed(2)} page(s)`)
      .attr("class", "avgCell");
    
    


  </script>


</div>