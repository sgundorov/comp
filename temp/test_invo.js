const http = require('http');
http.get('http://localhost/comp/invo.php', res => {
  let d = '';
  res.on('data', c => d += c);
  res.on('end', () => {
    require('fs').writeFileSync('C:\\xa\\htdocs\\comp\\temp\\invo_dump.html', d);
    console.log('Saved', d.length, 'bytes');
    console.log('searchForm:', d.indexOf('searchForm') > -1);
    console.log('searchCondBtn:', d.indexOf('searchCondBtn') > -1);
    console.log('searchToggleBtn:', d.indexOf('searchToggleBtn') > -1);
    console.log('sortBtn:', d.indexOf('sortBtn') > -1);
    console.log('columnsBtn:', d.indexOf('columnsBtn') > -1);
    console.log('closeAllPanels:', d.indexOf('closeAllPanels') > -1);
    console.log('SearchPanel.init:', d.indexOf('SearchPanel.init') > -1);
    console.log('SortPanel.init:', d.indexOf('SortPanel.init') > -1);
    console.log('ColumnsPanel.init:', d.indexOf('ColumnsPanel.init') > -1);
    console.log('InlineEdit.init:', d.indexOf('InlineEdit.init') > -1);
    console.log('ColumnResize.init:', d.indexOf('ColumnResize.init') > -1);
    console.log('RowSelect.init:', d.indexOf('RowSelect.init') > -1);
    console.log('SelectionToolbar.init:', d.indexOf('SelectionToolbar.init') > -1);
    console.log('column-filter.js:', d.indexOf('column-filter.js') > -1);
    console.log('render_form_modal_script:', d.indexOf('render_form_modal_script') > -1 || d.indexOf('form-modal') > -1);
  });
});
