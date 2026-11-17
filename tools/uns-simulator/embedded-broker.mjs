// Broker MQTT minimal pour tester sans Docker. Local uniquement : lié à 127.0.0.1, anonyme.
import net from 'node:net';

export async function startEmbeddedBroker({ port = 1883, wsPort } = {}) {
  let Aedes;
  try {
    ({ default: Aedes } = await import('aedes'));
  } catch {
    throw new Error('aedes introuvable : lancez `npm install` dans tools/uns-simulator (ou utilisez infra/compose.yaml).');
  }
  const aedes = typeof Aedes.createBroker === 'function' ? Aedes.createBroker() : new Aedes();
  const tcp = net.createServer(aedes.handle);
  await new Promise((resolve, reject) => {
    tcp.once('error', reject);
    tcp.listen(port, '127.0.0.1', resolve);
  });
  console.error(`Broker embarqué : mqtt://127.0.0.1:${port}`);

  let wss;
  if (wsPort) {
    const { WebSocketServer, createWebSocketStream } = await import('ws');
    wss = new WebSocketServer({ host: '127.0.0.1', port: wsPort });
    wss.on('connection', (ws) => aedes.handle(createWebSocketStream(ws)));
    console.error(`Broker embarqué (WebSocket) : ws://127.0.0.1:${wsPort}`);
  }

  return () => new Promise((resolve) => {
    wss?.close();
    tcp.close(() => aedes.close(resolve));
  });
}
