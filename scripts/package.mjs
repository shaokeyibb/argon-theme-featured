import { ZipArchive } from 'archiver';
import { createWriteStream, mkdirSync, statSync, readFileSync, writeFileSync } from 'node:fs';
import { join } from 'node:path';
import { createHash } from 'node:crypto';
import { root, verifyRelease } from './release-files.mjs';
const {files,version} = verifyRelease();
const directory = join(root,'release');
mkdirSync(directory,{recursive:true});
const destination = join(directory,'argon-theme-featured.zip');
const output = createWriteStream(destination);
const archive = new ZipArchive({zlib:{level:9}});
const finished = new Promise((resolve,reject) => { output.on('close',resolve); output.on('error',reject); archive.on('error',reject); });
archive.pipe(output);
// Buffers preserve insertion order; archive.file() queues asynchronous stat jobs.
for (const file of files) archive.append(readFileSync(join(root,file)),{name:`argon-theme-featured/${file}`,date:new Date('2026-01-01T00:00:00Z'),mode:0o644});
await archive.finalize(); await finished;
const sha256 = createHash('sha256').update(readFileSync(destination)).digest('hex');
writeFileSync(join(directory,'argon-theme-featured.sha256'),`${sha256}  argon-theme-featured.zip\n`);
console.log(`Argon ${version}: ${files.length} files, ${statSync(destination).size} bytes\n${destination}\nSHA256 ${sha256}`);
