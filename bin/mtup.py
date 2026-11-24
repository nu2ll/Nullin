import asyncio
import json
import os
import sys
import time

from telethon import TelegramClient, types, utils, functions
from telethon.sessions import StringSession


def emit(obj):
    print(json.dumps(obj, ensure_ascii=False), flush=True)


def to_jpeg(path):
    # Telegram photos must be JPEG/PNG; web images may be webp/gif/etc. Re-encode only when needed.
    try:
        from PIL import Image
        with Image.open(path) as im:
            if im.format in ('JPEG', 'PNG'):
                return path
            out = path + '.nl.jpg'
            im.convert('RGB').save(out, 'JPEG', quality=90)
            return out
    except Exception:
        return path


async def main():
    a = json.loads(os.environ['NL_JOB'])
    path = a['session_file']
    saved = open(path).read().strip() if os.path.exists(path) else ''
    client = TelegramClient(StringSession(saved), int(a['api_id']), a['api_hash'],
                            connection_retries=3, request_retries=3, flood_sleep_threshold=90)
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

    if a.get('slides'):
        # Direct slideshow: upload every file over MTProto, then send ONE rich message
        # whose HTML references them (tg://photo?id=X / tg://video?id=X) and whose `files`
        # carry the matching InputRichFile* entries. No Bot API file_id, no forwarding.
        slides = a['slides']
        total = sum(os.path.getsize(s['file']) for s in slides) or 1
        base = [0]
        files = []
        tmp_files = []
        for s in slides:
            if s['kind'] == 'photo':
                orig = s['file']
                s['file'] = to_jpeg(orig)
                if s['file'] != orig:
                    tmp_files.append(s['file'])
            size = os.path.getsize(s['file'])

            def prog(done, _t, _b=base[0]):
                progress(_b + done, total)

            fh = await client.upload_file(s['file'], part_size_kb=512, file_name=s.get('name') or None,
                                          progress_callback=prog)
            if s['kind'] == 'video':
                th = await client.upload_file(s['thumb']) if s.get('thumb') else None
                m = types.InputMediaUploadedDocument(
                    file=fh, mime_type='video/mp4', thumb=th,
                    attributes=[types.DocumentAttributeFilename(s.get('name') or 'video.mp4'),
                                types.DocumentAttributeVideo(duration=int(s.get('duration') or 0),
                                                             w=int(s.get('width') or 0), h=int(s.get('height') or 0),
                                                             supports_streaming=True)])
                up = await client(functions.messages.UploadMediaRequest(peer=peer, media=m))
                files.append(types.InputRichFileDocument(id=s['id'], document=utils.get_input_document(up.document)))
            else:
                m = types.InputMediaUploadedPhoto(file=fh)
                up = await client(functions.messages.UploadMediaRequest(peer=peer, media=m))
                files.append(types.InputRichFilePhoto(id=s['id'], photo=utils.get_input_photo(up.photo)))
            base[0] += size
        reply = types.InputReplyToMessage(reply_to_msg_id=int(a['reply_to'])) if a.get('reply_to') else None
        res = await client(functions.messages.SendMessageRequest(
            peer=peer, message='', reply_to=reply,
            rich_message=types.InputRichMessageHTML(html=a['html'], rtl=True if a.get('rtl') else None, files=files)))
        for t in tmp_files:
            try:
                os.unlink(t)
            except OSError:
                pass
        mid = getattr(res, 'id', None)
        if mid is None:
            for u in getattr(res, 'updates', []):
                if isinstance(u, types.UpdateMessageID):
                    mid = u.id
                    break
        emit({'ok': True, 'msg_id': mid})
        await client.disconnect()
        return

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
    emit({'ok': False, 'error': type(e).__name__ + ': ' + str(e)[:300]})
    sys.exit(1)
