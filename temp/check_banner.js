const http = require('http');
http.get('http://localhost/comp/city.php?q=test', res => {
  let d = '';
  res.on('data', c => d += c);
  res.on('end', () => {
    const banners = d.match(/mode-banner/g);
    console.log('mode-banner count:', banners ? banners.length : 0);
    const filterBanners = d.match(/id="filterBanner"/g);
    console.log('filterBanner count:', filterBanners ? filterBanners.length : 0);
    const idx = d.indexOf('filterBanner');
    if (idx > 0) {
      console.log('Context:', d.substring(Math.max(0, idx - 200), idx + 200).replace(/\n/g, ' '));
    }
  });
});
