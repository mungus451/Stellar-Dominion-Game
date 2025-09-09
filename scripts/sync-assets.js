const { spawn } = require('child_process');
const path = require('path');
const fs = require('fs');

function run(cmd, args, opts = {}) {
  return new Promise((resolve, reject) => {
    const p = spawn(cmd, args, { stdio: 'inherit', shell: false, ...opts });
    p.on('close', code => code === 0 ? resolve() : reject(new Error(`${cmd} exited ${code}`)));
  });
}

function runCapture(cmd, args, opts = {}) {
  return new Promise((resolve, reject) => {
    const p = spawn(cmd, args, { stdio: ['ignore', 'pipe', 'pipe'], shell: false, ...opts });
    let out = '';
    let err = '';
    p.stdout.on('data', d => out += d.toString());
    p.stderr.on('data', d => err += d.toString());
    p.on('close', code => code === 0 ? resolve(out.trim()) : reject(new Error(err.trim() || `${cmd} exited ${code}`)));
  });
}

async function main(){
  const pkg = require('../package.json');
  const stage = process.env.STAGE || pkg.config && pkg.config.stage || 'dev';
  const serviceName = process.env.SERVICE_NAME || pkg.name || 'starlight-dominion';
  const defaultBucket = `${serviceName}-assets-${stage}`;
  const bucket = process.env.BUCKET || defaultBucket;

  console.log('Syncing assets to', bucket);

  const assetsDir = path.resolve(__dirname, '..', 'Stellar-Dominion', 'public', 'assets');
  const favicon = path.resolve(__dirname, '..', 'Stellar-Dominion', 'public', 'assets', 'img', 'favicon.ico');
  const robots = path.resolve(__dirname, '..', 'Stellar-Dominion', 'public', 'robots.txt');

  try{
    // Check if bucket exists first (head-bucket)
    try{
      await runCapture('aws', ['s3api', 'head-bucket', '--bucket', bucket]);
    } catch (e) {
      console.warn(`Bucket s3://${bucket} does not exist yet; skipping upload.`);
      console.warn('You can run the sync later with: BUCKET=' + bucket + ' npm run sync-assets');
      process.exit(0);
    }
    if (fs.existsSync(assetsDir)){
      await run('aws', ['s3', 'sync', assetsDir, `s3://${bucket}/assets/`, '--acl', 'private']);
    } else {
      console.warn('Assets dir not found:', assetsDir);
    }

    if (fs.existsSync(favicon)){
      await run('aws', ['s3', 'cp', favicon, `s3://${bucket}/favicon.ico`, '--acl', 'private']);
    } else {
      console.warn('favicon not found:', favicon);
    }

    if (fs.existsSync(robots)){
      await run('aws', ['s3', 'cp', robots, `s3://${bucket}/robots.txt`, '--acl', 'private']);
    } else {
      console.warn('robots.txt not found:', robots);
    }

    console.log('Assets sync complete');
  } catch (err){
    console.error('Assets sync failed:', err.message);
    process.exit(1);
  }
}

main();
