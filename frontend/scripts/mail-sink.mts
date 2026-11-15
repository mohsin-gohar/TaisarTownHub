/**
 * Minimal SMTP sink — a *test transport* used to prove the real mail pipeline
 * (Laravel SMTP transport -> queue worker -> provider) end to end without
 * inventing a fake provider in the application code.
 *
 * It accepts a message on 127.0.0.1:1025, writes it to disk and prints a
 * summary. Nothing in the application ships with this; it is a harness.
 *
 * Usage: npx tsx scripts/mail-sink.mts [port] [outputDir]
 */
import { createServer, type Socket } from 'node:net';
import { mkdirSync, writeFileSync } from 'node:fs';
import path from 'node:path';

const port = Number(process.argv[2] || 1025);
const outDir = path.resolve(process.argv[3] || path.join(process.cwd(), 'audit', 'mail'));
mkdirSync(outDir, { recursive: true });

let counter = 0;

const server = createServer((socket: Socket) => {
  let stage: 'command' | 'data' = 'command';
  let buffer = '';
  let message = '';
  const chunks: string[] = [];

  const send = (line: string) => socket.write(`${line}\r\n`);

  send('220 taisartownhub-test-sink ESMTP ready');

  socket.on('data', (chunk) => {
    buffer += chunk.toString('utf8');

    let index: number;
    while ((index = buffer.indexOf('\r\n')) !== -1) {
      const line = buffer.slice(0, index);
      buffer = buffer.slice(index + 2);

      if (stage === 'data') {
        if (line === '.') {
          stage = 'command';
          counter += 1;
          const file = path.join(outDir, `message-${String(counter).padStart(3, '0')}.eml`);
          const raw = chunks.join('\r\n');
          writeFileSync(file, raw, 'utf8');
          const subject = /Subject:\s*(.+)/i.exec(raw)?.[1]?.trim() ?? '(no subject)';
          const to = /To:\s*(.+)/i.exec(raw)?.[1]?.trim() ?? '(no recipient)';
          const links = raw.match(/https?:\/\/\S+/g) ?? [];
          console.log(
            `[sink] captured message #${counter} -> ${path.basename(file)}\n` +
              `       to=${to}\n       subject=${subject}\n` +
              `       links=${links.length ? links.slice(0, 3).join(' , ') : '(none)'}`,
          );
          chunks.length = 0;
          message = '';
          send('250 2.0.0 Ok: queued');
        } else {
          chunks.push(line);
        }
        continue;
      }

      const upper = line.toUpperCase();
      if (upper.startsWith('EHLO')) {
        // Multi-line greeting: the final line must start with "250 " (space),
        // otherwise SMTP clients wait forever for the response terminator.
        send('250-mail.taisartownhub.test');
        send('250-SIZE 10485760');
        send('250 8BITMIME');
      } else if (upper.startsWith('HELO')) send('250 mail.taisartownhub.test');
      else if (upper.startsWith('MAIL FROM')) send('250 2.1.0 Ok');
      else if (upper.startsWith('RCPT TO')) send('250 2.1.5 Ok');
      else if (upper.startsWith('DATA')) {
        stage = 'data';
        send('354 End data with <CR><LF>.<CR><LF>');
      } else if (upper.startsWith('RSET')) send('250 2.0.0 Ok');
      else if (upper.startsWith('NOOP')) send('250 2.0.0 Ok');
      else if (upper.startsWith('QUIT')) {
        send('221 2.0.0 Bye');
        socket.end();
      } else send('250 2.0.0 Ok');
    }
  });

  socket.on('error', () => {
    /* the harness ignores client disconnects */
  });
});

server.listen(port, '127.0.0.1', () => {
  console.log(`[sink] listening on 127.0.0.1:${port}, writing to ${outDir}`);
});
