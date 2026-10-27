@php($teacher = config('avatar.model.name'))

<p>Pas besoin de connaître des milliers de mots pour vous débrouiller : un petit nombre de phrases revient tous les jours. Voici cinquante phrases courtes, en anglais américain, classées par situation. Lisez-les à voix haute, puis essayez de les dire sans regarder la traduction.</p>

<h2>Saluer et prendre des nouvelles</h2>
<ul class="phrases">
    <x-phrase en="Good morning!" fr="Bonjour ! (le matin)" />
    <x-phrase en="Good afternoon!" fr="Bonjour ! (l'après-midi)" />
    <x-phrase en="How are you?" fr="Comment allez-vous ?" />
    <x-phrase en="I'm fine, thank you. And you?" fr="Je vais bien, merci. Et vous ?" />
    <x-phrase en="See you tomorrow!" fr="À demain !" />
    <x-phrase en="Have a nice day!" fr="Bonne journée !" />
</ul>

<h2>Comprendre et se faire comprendre</h2>
<ul class="phrases">
    <x-phrase en="Sorry, I don't understand." fr="Désolé(e), je ne comprends pas." />
    <x-phrase en="Can you repeat, please?" fr="Pouvez-vous répéter, s'il vous plaît ?" />
    <x-phrase en="Can you speak more slowly, please?" fr="Pouvez-vous parler plus lentement, s'il vous plaît ?" />
    <x-phrase en="What does this word mean?" fr="Que veut dire ce mot ?" />
    <x-phrase en="How do you say this in English?" fr="Comment dit-on ça en anglais ?" />
    <x-phrase en="How do you spell it?" fr="Comment ça s'écrit ?" />
    <x-phrase en="I'm learning English." fr="J'apprends l'anglais." />
</ul>
<p class="tip"><strong>À retenir en premier :</strong> « Can you repeat, please? » et « Can you speak more slowly, please? ». Avec ces deux phrases, vous ne restez jamais bloqué dans une conversation.</p>

<h2>Poser des questions</h2>
<ul class="phrases">
    <x-phrase en="Where is the bathroom?" fr="Où sont les toilettes ?" />
    <x-phrase en="What time is it?" fr="Quelle heure est-il ?" />
    <x-phrase en="Can you help me, please?" fr="Pouvez-vous m'aider, s'il vous plaît ?" />
    <x-phrase en="Is it far from here?" fr="Est-ce loin d'ici ?" />
    <x-phrase en="What do you think?" fr="Qu'en pensez-vous ?" />
    <x-phrase en="Do you have a minute?" fr="Avez-vous une minute ?" />
</ul>
<p class="tip"><strong>Anglais américain :</strong> « bathroom » ou « restroom » pour les toilettes. « Toilet » désigne surtout la cuvette elle-même.</p>

<h2>Acheter et payer</h2>
<ul class="phrases">
    <x-phrase en="How much is it?" fr="Combien ça coûte ?" />
    <x-phrase en="It's too expensive." fr="C'est trop cher." />
    <x-phrase en="Can you lower the price?" fr="Pouvez-vous baisser le prix ?" />
    <x-phrase en="I'll take two, please." fr="J'en prends deux, s'il vous plaît." />
    <x-phrase en="Do you have a smaller size?" fr="Avez-vous une taille plus petite ?" />
    <x-phrase en="Can I pay by mobile money?" fr="Puis-je payer par Mobile Money ?" />
    <x-phrase en="Can I have the receipt, please?" fr="Puis-je avoir le reçu, s'il vous plaît ?" />
</ul>

<h2>Se déplacer</h2>
<ul class="phrases">
    <x-phrase en="I'm going to the market." fr="Je vais au marché." />
    <x-phrase en="Can you take me to the airport?" fr="Pouvez-vous m'emmener à l'aéroport ?" />
    <x-phrase en="Turn left at the next street." fr="Tournez à gauche à la prochaine rue." />
    <x-phrase en="Stop here, please." fr="Arrêtez-vous ici, s'il vous plaît." />
    <x-phrase en="How long does it take?" fr="Combien de temps ça prend ?" />
    <x-phrase en="I'm lost." fr="Je suis perdu(e)." />
</ul>

<h2>Au téléphone</h2>
<ul class="phrases">
    <x-phrase en="Hello, this is Kodjo speaking." fr="Allô, c'est Kodjo à l'appareil." />
    <x-phrase en="Can I speak to Mrs. Mensah, please?" fr="Puis-je parler à Madame Mensah, s'il vous plaît ?" />
    <x-phrase en="Can you hear me?" fr="Vous m'entendez ?" />
    <x-phrase en="The connection is bad." fr="La communication est mauvaise." />
    <x-phrase en="I'll call you back later." fr="Je vous rappelle plus tard." />
    <x-phrase en="Can you send me a message on WhatsApp?" fr="Pouvez-vous m'envoyer un message sur WhatsApp ?" />
</ul>

<h2>Politesse : remercier et s'excuser</h2>
<ul class="phrases">
    <x-phrase en="Thank you very much." fr="Merci beaucoup." />
    <x-phrase en="You're welcome." fr="Je vous en prie. / De rien." />
    <x-phrase en="Excuse me." fr="Excusez-moi (pour attirer l'attention ou passer)." />
    <x-phrase en="I'm sorry, I'm late." fr="Désolé(e), je suis en retard." />
    <x-phrase en="No problem." fr="Pas de problème." />
    <x-phrase en="Congratulations!" fr="Félicitations !" />
</ul>
<p class="tip"><strong>« Excuse me » ou « Sorry » ?</strong> « Excuse me » avant de déranger quelqu'un ; « Sorry » après une erreur ou pour s'excuser.</p>

<h2>À l'école et au travail</h2>
<ul class="phrases">
    <x-phrase en="I have a question." fr="J'ai une question." />
    <x-phrase en="Can you explain it again?" fr="Pouvez-vous l'expliquer encore une fois ?" />
    <x-phrase en="I agree with you." fr="Je suis d'accord avec vous." />
    <x-phrase en="I'm working on it." fr="Je m'en occupe." />
    <x-phrase en="Let's start the meeting." fr="Commençons la réunion." />
    <x-phrase en="I'll send you the document today." fr="Je vous envoie le document aujourd'hui." />
</ul>

<h2>Comment les retenir</h2>
<p>Ne cherchez pas à tout apprendre en une fois. Choisissez cinq phrases par jour, dites-les à voix haute dans une petite situation imaginée (au marché, au téléphone), puis reprenez celles de la veille avant d'en ajouter de nouvelles. Revoir une phrase le lendemain, puis quelques jours plus tard, aide beaucoup à la garder en mémoire. Avec {{ $teacher }}, les phrases du jour sont au tableau et vous les redites dans une conversation : c'est le meilleur moyen de passer de la liste à la parole.</p>
