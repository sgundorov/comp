const http = require('http');
http.get('http://localhost/comp/city.php?q=test&cols=city&cond=contains&sf=1', res => {
  let d = '';
  res.on('data', c => d += c);
  res.on('end', () => {
    const banners = d.match(/mode-banner/g);
    console.log('mode-banner count:', banners ? banners.length : 0);
    const filterBanners = d.match(/id="filterBanner"/g);
    console.log('filterBanner count:', filterBanners ? filterBanners.length : 0);
    const idx = d.indexOf('filterBanner');
    if (idx > 0) {
      console.log('Context around filterBanner:', d.substring(Math.max(0, idx - 100), idx + 300).replace(/\n/g, ' '));
    }
    // Also look for the search filter text
    const filterIdx = d.indexOf('Быстрый фильтр');
    if (filterIdx > 0) {
      console.log('\nContext around "Быстрый фильтр":', d.substring(Math.max(0, filterIdx - 200), filterIdx + 300).replace(/\n/g, ' '));
    }
  });
});
