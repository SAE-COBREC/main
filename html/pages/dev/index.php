<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Documentation Delivraptor</title>
  <link rel="stylesheet" href="../../styles/Header/stylesHeader.css">
  <link rel="stylesheet" href="../../styles/Footer/stylesFooter.css">
  <style>
    :root{--bg:#fafafa;--card:#fff;--muted:#666}
    body{font-family:Inter, system-ui, -apple-system, 'Segoe UI', Roboto, 'Helvetica Neue', Arial;margin:0;background:var(--bg);color:#222}
    .layout{display:flex;min-height:80vh}
    .sidebar{width:260px;background:var(--card);border-right:1px solid #eee;padding:1rem;box-shadow:2px 0 12px rgba(0,0,0,0.02)}
    .sidebar h2{margin:0 0 1rem 0;font-size:1.1rem}
    .files{list-style:none;padding:0;margin:0;max-height:75vh;overflow:auto}
    .files li{margin:0 0 .25rem}
    .files a{display:block;padding:.4rem .6rem;border-radius:6px;color:#0366d6;text-decoration:none}
    .files a:hover{background:#f1f8ff}
    .main{flex:1;padding:1.25rem}
    .doc-block{background:var(--card);padding:1rem;border-radius:8px;margin-bottom:1rem;box-shadow:0 6px 18px rgba(0,0,0,0.03)}
    .doc-block h2{margin-top:0}
    .controls{display:flex;gap:.5rem;align-items:center;margin-bottom:.75rem}
    @media(max-width:800px){.layout{flex-direction:column}.sidebar{width:100%;order:2}.main{order:1}}
  </style>
</head>
<?php require_once "../../partials/header.php" ?>
<?php
// Scanner le dossier de documentation
$docDir = realpath(__DIR__ . '/../../../Delivraptor/rendu/doc');
$files = [];
if ($docDir && is_dir($docDir)){
    $dh = opendir($docDir);
    while(($f = readdir($dh)) !== false){
        if ($f === '.' || $f === '..') continue;
        $path = $docDir . DIRECTORY_SEPARATOR . $f;
        if (is_file($path)){
            $ext = strtolower(pathinfo($f, PATHINFO_EXTENSION));
            if (in_array($ext, ['md','markdown','txt'])){
                $files[] = $f;
            }
        }
    }
    closedir($dh);
}
sort($files);
?>

<main class="layout">
  <aside class="sidebar">
    <h2>Documentation Delivraptor</h2>
    <div class="controls">
      <button id="refreshList" style="padding:.4rem .6rem;border-radius:6px;border:0;background:#0078d4;color:#fff;cursor:pointer">Rafraîchir</button>
      <button id="collapseBtn" style="padding:.4rem .6rem;border-radius:6px;border:0;background:#6c757d;color:#fff;cursor:pointer">Toggle</button>
    </div>
    <ul class="files" id="fileList">
      <?php foreach($files as $f): ?>
        <li><a href="#" data-file="<?php echo htmlspecialchars($f, ENT_QUOTES); ?>"><?php echo htmlspecialchars($f); ?></a></li>
      <?php endforeach; ?>
    </ul>
  </aside>

  <section class="main">
    <h1>Documents</h1>
    <p style="color:var(--muted)">Sélectionnez un fichier à gauche, ou faites défiler pour lire tous les documents.</p>

    <div id="contentArea"></div>
  </section>
</main>

<script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script>
<script>
  const files = <?php echo json_encode($files); ?>;
  const fileListEl = document.getElementById('fileList');
  const contentArea = document.getElementById('contentArea');

  async function loadFile(name, anchorScroll=false){
    try{
      const res = await fetch('getdoc.php?file=' + encodeURIComponent(name));
      if(!res.ok) throw new Error('HTTP '+res.status);
      const data = await res.json();
      const id = 'doc-' + name.replace(/[^a-z0-9\-]/gi,'-');
      const block = document.createElement('div'); block.className='doc-block'; block.id = id;
      block.innerHTML = '<h2>' + (data.name || name) + '</h2><div class="md">' + marked.parse(data.content || '') + '</div>';
      contentArea.appendChild(block);
      if(anchorScroll) location.hash = '#' + id;
    }catch(e){
      console.error(e);
    }
  }

  async function loadAll(){
    contentArea.innerHTML='';
    for(const f of files){ await loadFile(f); }
  }

  // Sidebar click => scroll to file if already loaded, otherwise load and scroll
  fileListEl.addEventListener('click', async (ev)=>{
    const a = ev.target.closest('a[data-file]');
    if(!a) return;
    ev.preventDefault();
    const file = a.getAttribute('data-file');
    const id = 'doc-' + file.replace(/[^a-z0-9\-]/gi,'-');
    const existing = document.getElementById(id);
    if(existing){ existing.scrollIntoView({behavior:'smooth'}); return; }
    await loadFile(file, true);
  });

  document.getElementById('refreshList').addEventListener('click', ()=> location.reload());
  document.getElementById('collapseBtn').addEventListener('click', ()=> document.querySelector('.sidebar').classList.toggle('collapsed'));

  // initial: load all documents in sequence
  loadAll();
</script>

<?php require_once "../../partials/footer.html" ?>
</html>
