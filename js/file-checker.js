fetch(CCL_FileCheck.ajax_url + '?action=ccl_check_file_size')
  .then(res => res.json())
  .then(data => {
    if (Array.isArray(data) && data.length > 0) {
      const alertText = data.map(file =>
        `⚠️ ${file.filename} (${file.humanSize})`
      ).join('\n');

      alert("Fichiers trop volumineux détectés :\n\n" + alertText);
    }
  });
