"""Monta docs/img/demo.gif a partir dos quadros gravados por demo-gif.mjs."""
import json
from PIL import Image

frames = json.load(open('storage/app/gif-frames/frames.json'))
images, durations, last = [], [], None
for path, ms in frames:
    im = Image.open(path).convert('RGB')
    im = im.resize((960, int(im.height * 960 / im.width)), Image.LANCZOS)
    # quadros repetidos (ex.: esperando a fila) viram um só, com no máx. 1,2 s
    if images and im.tobytes() == last:
        durations[-1] = min(durations[-1] + ms, 1200)
        continue
    last = im.tobytes()
    images.append(im.quantize(colors=128, method=Image.Quantize.MEDIANCUT))
    durations.append(ms)

images[0].save('docs/img/demo.gif', save_all=True, append_images=images[1:], duration=durations, loop=0, optimize=True)
print('docs/img/demo.gif')
