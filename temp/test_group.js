const http = require('http');
http.get('http://localhost/comp/group.php', res => {
  let d = '';
  res.on('data', c => d += c);
  res.on('end', () => {
    require('fs').writeFileSync('C:\\xa\\htdocs\\comp\\temp\\group_dump.html', d);
    console.log('Saved', d.length, 'bytes');
    console.log('searchForm:', d.indexOf('searchForm') > -1);
    console.log('SearchPanel.init:', d.indexOf('SearchPanel.init') > -1);
    console.log('SortPanel.init:', d.indexOf('SortPanel.init') > -1);
    console.log('closeAllPanels:', d.indexOf('closeAllPanels') > -1);
    console.log('ColumnFilter.init:', d.indexOf('ColumnFilter.init') > -1);
  });
});
