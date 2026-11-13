# BULIG Defense Panel

A practice room for the BULIG Quest capstone defense. A panel of three asks questions out loud, you answer by speaking, and the app writes down what you said and checks it.

## What it does
- **Mock defense**: 5 to 15 questions in the order a real panel asks them, starting with "Briefly introduce your study".
- **Practice a topic**: questions from one topic only (overview, objectives, methodology, system, technology and database, results, privacy and security, next steps, random).
- **Random question**: one surprise question.
- **Answer by voice**: tap the microphone. Your words appear as you speak, a timer runs, and the answer is recorded so you can play it back. You can also type.
- **Feedback**:
  - a score from 0 to 100 and 1 to 3 stars
  - the key points you said and missed, with your words in green and filler words ("um", "uh", "like", "ano"…) in orange
  - words a minute and answer length
  - a sentence to add for each missed point
  - a better answer you can listen to
  - tips
- **Your own questions**, **history** and **settings** are saved in the browser on this device.

The 87 questions and model answers come from the BULIG Quest system and every chapter of the Capstone 2 manuscript (assets/questions.js and assets/questions-more.js). Edit those files to change or add questions.

## How to run it
It is a static site: no database and no PHP.
- Upload the folder to any web host (HTTPS), or
- on a computer, run `python -m http.server 8000` in this folder and open http://localhost:8000

Use **Chrome or Edge** for answering by voice. They send the speech to their own speech service, so an Internet connection is needed. Other browsers can still type their answers. Speech to text needs HTTPS or localhost; it does not work when index.html is opened as a file.

## On a phone
- Open the web address in **Chrome (Android)** or **Safari (iPhone)**.
- Install it:
  - Android: tap **Install** on Home, or the browser menu › **Add to Home screen**.
  - iPhone: tap **Share** › **Add to Home Screen**.
- It then opens like an app ("Defense Panel" with the robot icon), with a bottom tab bar and a big yellow button to start a mock defense.
- Allow the microphone when asked. Answering by voice needs the Internet. With no Internet, the app still opens, and you can study the questions and type your answers.
- The phone has to open the app over **HTTPS**, so put it on a web host (for example a folder on your Hostinger site). A phone cannot use speech to text from a plain http:// address on your laptop.
