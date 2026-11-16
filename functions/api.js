// Cloudflare Pages Function - API Endpoint for Cerco de Jericó (Paróquia São José - SJB)
function getCercoJericoSlots() {
  const slots = [];
  for (let h = 0; h < 24; h++) {
    const time = `${String(h).padStart(2, '0')}:00`;
    const notes = h < 6 ? "Madrugada" : "";
    slots.push({ time, adorers: [], notes });
  }
  return slots;
}

const CORS_HEADERS = {
  'Access-Control-Allow-Origin': '*',
  'Access-Control-Allow-Methods': 'GET, POST, OPTIONS',
  'Access-Control-Allow-Headers': 'Content-Type',
  'Content-Type': 'application/json; charset=utf-8'
};

const memoryStore = new Map();

export async function onRequest(context) {
  const { request, env } = context;
  const kv = env.ADORACAO_KV || env.KV;

  if (request.method === 'OPTIONS') {
    return new Response(null, { headers: CORS_HEADERS });
  }

  const url = new URL(request.url);

  const defaultQuoteText = "“Eu e minha casa serviremos ao Senhor.” (Js 24,15)\n“Buscai o Senhor enquanto Ele se deixa encontrar.” (Is 55,6)";
  const defaultQuoteAuthor = "Cerco de Jericó · 15 a 22 de Novembro · Paróquia São José";

  async function getData(dateStr) {
    const key = `cerco_sjb_${dateStr}`;
    let data = null;

    if (kv) {
      try {
        const val = await kv.get(key);
        if (val) data = JSON.parse(val);
      } catch (e) {
        console.error('KV Read Error:', e);
      }
    } else {
      data = memoryStore.get(key);
    }

    if (!data) {
      data = {
        date: dateStr,
        title: "Cerco de Jericó — Adoração Dia e Noite",
        community: "Paróquia São José — São José do Barreiro",
        quote_text: defaultQuoteText,
        quote_author: defaultQuoteAuthor,
        slots: getCercoJericoSlots(),
        last_updated: new Date().toISOString()
      };
      await saveData(dateStr, data);
    } else if (!data.quote_text) {
      data.quote_text = defaultQuoteText;
      data.quote_author = defaultQuoteAuthor;
    }

    return data;
  }

  async function saveData(dateStr, data) {
    const key = `cerco_sjb_${dateStr}`;
    data.last_updated = new Date().toISOString();
    const jsonStr = JSON.stringify(data);

    if (kv) {
      try {
        await kv.put(key, jsonStr);
      } catch (e) {
        console.error('KV Write Error:', e);
      }
    }
    memoryStore.set(key, data);
  }

  if (request.method === 'GET') {
    const date = url.searchParams.get('date') || new Date().toISOString().split('T')[0];
    const data = await getData(date);
    return new Response(JSON.stringify({ status: 'success', data }), { headers: CORS_HEADERS });
  }

  if (request.method === 'POST') {
    let body = {};
    try {
      body = await request.json();
    } catch (e) {
      // empty body
    }

    const action = body.action || '';
    const date = body.date || new Date().toISOString().split('T')[0];

    let data = await getData(date);

    if (action === 'save_quote') {
      data.quote_text = (body.quote_text || "").trim();
      data.quote_author = (body.quote_author || "").trim();
      await saveData(date, data);
      return new Response(JSON.stringify({ status: 'success', message: 'Frase atualizada com sucesso!', data }), { headers: CORS_HEADERS });
    }

    if (action === 'book') {
      const { time, name, intention } = body;
      if (!name || !time) {
        return new Response(JSON.stringify({ status: 'error', message: 'Nome e horário são obrigatórios.' }), { headers: CORS_HEADERS });
      }

      let updated = false;
      for (let slot of data.slots) {
        if (slot.time === time) {
          if (!slot.adorers) slot.adorers = [];
          slot.adorers.push({
            name: name.trim(),
            intention: intention ? intention.trim() : '',
            created_at: new Date().toISOString()
          });
          updated = true;
          break;
        }
      }

      if (updated) {
        await saveData(date, data);
        return new Response(JSON.stringify({ status: 'success', message: 'Horário agendado com sucesso!', data }), { headers: CORS_HEADERS });
      } else {
        return new Response(JSON.stringify({ status: 'error', message: 'Horário não encontrado.' }), { headers: CORS_HEADERS });
      }
    }

    if (action === 'remove') {
      const { time, index } = body;
      const idx = parseInt(index, 10) || 0;

      for (let slot of data.slots) {
        if (slot.time === time) {
          if (slot.adorers && slot.adorers[idx]) {
            slot.adorers.splice(idx, 1);
            break;
          }
        }
      }

      await saveData(date, data);
      return new Response(JSON.stringify({ status: 'success', message: 'Adorador removido com sucesso!', data }), { headers: CORS_HEADERS });
    }

    if (action === 'edit_adorer') {
      const { time, index, name, intention } = body;
      const idx = parseInt(index, 10) || 0;
      const cleanName = (name || '').trim();

      if (!cleanName || !time) {
        return new Response(JSON.stringify({ status: 'error', message: 'Nome e horário são obrigatórios.' }), { headers: CORS_HEADERS });
      }

      let updated = false;
      for (let slot of data.slots) {
        if (slot.time === time && slot.adorers && slot.adorers[idx]) {
          slot.adorers[idx].name = cleanName;
          slot.adorers[idx].intention = intention ? intention.trim() : '';
          slot.adorers[idx].updated_at = new Date().toISOString();
          updated = true;
          break;
        }
      }

      if (updated) {
        await saveData(date, data);
        return new Response(JSON.stringify({ status: 'success', message: 'Adorador atualizado com sucesso!', data }), { headers: CORS_HEADERS });
      }
      return new Response(JSON.stringify({ status: 'error', message: 'Adorador não encontrado.' }), { headers: CORS_HEADERS });
    }

    if (action === 'reset_day') {
      data.slots = getCercoJericoSlots();
      await saveData(date, data);
      return new Response(JSON.stringify({ status: 'success', message: 'Horários reiniciados para o padrão!', data }), { headers: CORS_HEADERS });
    }

    if (action === 'add_general_intention') {
      const name = (body.name || "Anônimo").trim();
      const intention = (body.intention || "").trim();

      if (!intention) {
        return new Response(JSON.stringify({ status: 'error', message: 'O pedido de oração não pode estar vazio.' }), { headers: CORS_HEADERS });
      }

      if (!Array.isArray(data.general_intentions)) {
        data.general_intentions = [];
      }

      data.general_intentions.push({
        name: name || "Anônimo",
        intention: intention,
        created_at: new Date().toISOString()
      });

      await saveData(date, data);
      return new Response(JSON.stringify({ status: 'success', message: 'Pedido de oração adicionado com sucesso!', data }), { headers: CORS_HEADERS });
    }

    if (action === 'save_all') {
      if (Array.isArray(body.slots)) {
        data.slots = body.slots;
        await saveData(date, data);
        return new Response(JSON.stringify({ status: 'success', message: 'Escala salva com sucesso!', data }), { headers: CORS_HEADERS });
      }
    }

    return new Response(JSON.stringify({ status: 'error', message: 'Ação desconhecida.' }), { headers: CORS_HEADERS });
  }

  return new Response(JSON.stringify({ status: 'error', message: 'Método não suportado' }), { status: 405, headers: CORS_HEADERS });
}
