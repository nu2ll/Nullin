import asyncio
import json
import logging
import os
import sys
import time

from telethon import TelegramClient, types, utils, functions
from telethon.sessions import StringSession


logging.basicConfig(level=logging.INFO, stream=sys.stderr, format='%(levelname)s %(name)s: %(message)s')


def emit(obj):
    print(json.dumps(obj, ensure_ascii=False), flush=True)


async def main():
    a = json.loads(os.environ['NL_JOB'])
    path = a['session_file']
    saved = open(path).read().strip() if os.path.exists(path) else ''
    kw = dict(connection_retries=3, request_retries=3, flood_sleep_threshold=90)
    try:
        client = TelegramClient(StringSession(saved), int(a['api_id']), a['api_hash'], raise_last_call_error=True, **kw)
    except TypeError:
        client = TelegramClient(StringSession(saved), int(a['api_id']), a['api_hash'], **kw)
    t = a.get('test')
    if t:
        client.session.set_dc(int(t['dc']), t['ip'], int(t['port']))
    await client.start(bot_token=a['bot_token'])
    fresh = client.session.save()
    if fresh != saved:
        tmp = path + '.tmp'
        with open(tmp, 'w') as f:
            f.write(fresh)
        os.replace(tmp, path)

    real, kind = utils.resolve_id(int(a['chat']))
    peer = await client.get_input_entity(kind(real))

    last = [0.0]

    def progress(done, total):
        now = time.monotonic()
        if now - last[0] >= 3 or done >= total:
            last[0] = now
            emit({'p': done, 't': total})

    if a.get('items'):
        media = []
        for it in a['items']:
            fh = await client.upload_file(it['file'], part_size_kb=512, file_name=it['name'])
            th = await client.upload_file(it['thumb']) if it.get('thumb') else None
            m = types.InputMediaUploadedDocument(
                file=fh, mime_type='audio/mpeg', thumb=th,
                attributes=[types.DocumentAttributeFilename(it['name']),
                            types.DocumentAttributeAudio(duration=int(it.get('duration') or 0),
                                                         title=it.get('title') or None, performer=it.get('performer') or None)])
            up = await client(functions.messages.UploadMediaRequest(peer=peer, media=m))
            media.append(types.InputSingleMedia(media=utils.get_input_media(up.document), message='',
                                                random_id=int.from_bytes(os.urandom(8), 'big', signed=True)))
        reply = types.InputReplyToMessage(reply_to_msg_id=int(a['reply_to'])) if a.get('reply_to') else None
        res = await client(functions.messages.SendMultiMediaRequest(peer=peer, multi_media=media, reply_to=reply))
        rid = {u.random_id: u.id for u in getattr(res, 'updates', []) if isinstance(u, types.UpdateMessageID)}
        emit({'ok': True, 'msg_ids': [rid.get(m.random_id) for m in media]})
        await client.disconnect()
        return

    attrs = [types.DocumentAttributeFilename(a['name'])]
    if a['kind'] == 'video':
        attrs.append(types.DocumentAttributeVideo(duration=int(a.get('duration') or 0), w=int(a.get('width') or 0),
                                                  h=int(a.get('height') or 0), supports_streaming=True))
    elif a['kind'] == 'audio':
        attrs.append(types.DocumentAttributeAudio(duration=int(a.get('duration') or 0),
                                                  title=a.get('title') or None, performer=a.get('performer') or None))

    msg = await client.send_file(
        peer, a['file'],
        caption=a.get('caption') or '', parse_mode='html',
        reply_to=a.get('reply_to') or None,
        attributes=attrs, thumb=a.get('thumb') or None,
        mime_type=a.get('mime') or None,
        supports_streaming=a['kind'] == 'video',
        force_document=a['kind'] == 'document',
        progress_callback=progress, part_size_kb=512,
    )
    emit({'ok': True, 'msg_id': msg.id})
    await client.disconnect()


try:
    asyncio.run(main())
except Exception as e:
    import traceback
    traceback.print_exc(file=sys.stderr)
    emit({'ok': False, 'error': type(e).__name__ + ': ' + str(e)[:300]})
    sys.exit(1)
