export function fontRequestBody(font) {
  const data = {
    name: font.name,
    author: font.author,
    family: font.family,
    license: font.license,
    licenseText: font.licenseText,
    sourceUrl: font.sourceUrl,
    tags: [...font.tags],
    previewText: font.previewText,
    dateAdded: font.dateAdded,
    supportsCyrillic: font.supportsCyrillic ?? false,
    faces: [],
  }
  const body = new FormData()
  let nextUpload = 0
  for (const face of font.faces) {
    const item = { weight: face.weight, style: face.style, files: [] }
    if (face.id) item.id = face.id
    for (const file of face.files) {
      if (file.blob) {
        const key = `file_${nextUpload++}`
        body.append(key, file.blob, file.name)
        item.files.push({ upload: key })
      } else {
        item.files.push({ id: file.id })
      }
    }
    data.faces.push(item)
  }
  if (!nextUpload) return data
  body.append('data', JSON.stringify(data))
  return body
}
