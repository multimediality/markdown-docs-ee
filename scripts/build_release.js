#!/usr/bin/env node

const fs = require('fs');
const path = require('path');
const { ZipArchive } = require('archiver');

const root = path.resolve(__dirname, '..');
const addonDir = path.join(root, 'markdown_docs');
// Zip-root copies. The customer manual is markdown_docs/README.md (EE
// renders it in the Add-on Manager as the in-app Manual); the repo-root
// README.md is developer-facing and is NOT shipped.
const docs = [
  { name: 'CHANGELOG.md', from: 'CHANGELOG.md' },
  { name: 'LICENSE', from: 'LICENSE' },
  { name: 'README.md', from: path.join('markdown_docs', 'README.md') },
];
const setupFile = path.join(addonDir, 'addon.setup.php');

function fail(message) {
  console.error(message);
  process.exit(1);
}

if (!fs.existsSync(addonDir)) {
  fail(`Unable to find addon directory at ${addonDir}`);
}

if (!fs.existsSync(setupFile)) {
  fail(`Unable to find addon.setup.php at ${setupFile}`);
}

// Check every zip-root file up front, before the previous same-version zip
// is deleted or the output stream opened: a fail() after that point would
// leave a 0-byte zip under the release name and take the good one with it.
docs.forEach((doc) => {
  const docPath = path.join(root, doc.from);
  if (!fs.existsSync(docPath)) {
    fail(`${docPath} not found — the release zip must carry ${doc.name}`);
  }
});

const setupContents = fs.readFileSync(setupFile, 'utf8');
const versionMatch = setupContents.match(/['"]version['"]\s*=>\s*['"]([^'"]+)['"]/i);

if (!versionMatch) {
  fail('Could not determine addon version from addon.setup.php');
}

const version = versionMatch[1].trim();

// Keep package.json version in sync (avoids version drift).
const packageJsonPath = path.join(root, 'package.json');
if (fs.existsSync(packageJsonPath)) {
  try {
    const pkg = JSON.parse(fs.readFileSync(packageJsonPath, 'utf8'));
    if (pkg && typeof pkg === 'object' && pkg.version !== version) {
      pkg.version = version;
      fs.writeFileSync(packageJsonPath, JSON.stringify(pkg, null, 2) + '\n', 'utf8');
      console.log(`Synced package.json version to ${version}`);
    }
  } catch (e) {
    console.warn('Warning: could not sync package.json version:', e.message);
  }
}

const distDir = path.join(root, 'dist');

if (!fs.existsSync(distDir)) {
  fs.mkdirSync(distDir, { recursive: true });
}

const zipName = `markdown_docs_${version}.zip`;
const zipPath = path.join(distDir, zipName);

if (fs.existsSync(zipPath)) {
  fs.unlinkSync(zipPath);
}

const output = fs.createWriteStream(zipPath);
const archive = new ZipArchive({ zlib: { level: 9 } });

output.on('close', () => {
  console.log(`Created ${zipPath} (${archive.pointer()} bytes)`);
});

archive.on('warning', (err) => {
  if (err.code === 'ENOENT') {
    console.warn(err.message);
  } else {
    throw err;
  }
});

archive.on('error', (err) => {
  throw err;
});

archive.pipe(output);
archive.directory(addonDir, 'markdown_docs');

docs.forEach((doc) => {
  archive.file(path.join(root, doc.from), { name: doc.name });
});

archive.finalize();
