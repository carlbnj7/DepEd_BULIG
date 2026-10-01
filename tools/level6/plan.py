"""Level 6 (Graded Reading Comprehension) lesson plan per grade, in module order.

Each lesson: (kind, lesson title, skill, [units]); each unit is one pupil activity = one story (or one
test section) with the PDF pages it uses.  kind: pre / lesson / check (formative assessment) / post.
TEACHER lists the pages pupils never see (lesson guides, answer keys, speed-rate guide).
"""

G1_LESSONS = [('My Friend', 'Noting Details', [10]), ('The Farmer', 'Noting Details', [11]), ('My Dolly', 'Noting Details', [12]),
              ('Joy’s Aya', 'Noting Details', [13]), ('Lita the Cow', 'Fantasy or Reality', [14]), ('Rosa’s Goat', 'Fantasy or Reality', [15]),
              ('Benny’s Happy Day', 'Fantasy or Reality', [16]), ('Lily’s Magical Pet', 'Fantasy or Reality', [17]),
              ('A Cold Bear', 'Sequencing Events', [18]), ('Dessert Time!', 'Sequencing Events', [19]),
              ('Harry and the Crayon', 'Sequencing Events', [20]), ('My Pet', 'Sequencing Events', [21]),
              ('Happenings', 'Cause and Effect', [22]), ('Activities', 'Cause and Effect', [23]), ('Good Ways', 'Cause and Effect', [24]),
              ('Snowball Fight', 'Cause and Effect', [25]), ('Alex and His Mother', 'Making Predictions/Conclusions', [26, 27]),
              ('Karen’s Puzzle', 'Making Predictions/Conclusions', [28, 29]),
              ('Watching a Circus Show', 'Making Predictions/Conclusions', [30, 31]), ('Fishing', 'Making Predictions/Conclusions', [32])]

G2_LESSONS = [('A Friend', 'Noting Details', [6]), ('My Pet Dog', 'Noting Details', [8]), ('At the Farm', 'Noting Details', [10]),
              ('The King', 'Noting Details', [12]), ('At the Park', 'Fantasy and Reality', [14]),
              ('Molly Reads Stories', 'Fantasy and Reality', [16]), ('Rex’s New Dog', 'Fantasy and Reality', [18]),
              ('My New Pet', 'Fantasy and Reality', [20]), ('Pushing and Pulling', 'Sequencing Events', [22]),
              ('Ann', 'Sequencing Events', [24]), ('My Mom', 'Sequencing Events', [26]), ('Pearl', 'Sequencing Events', [28]),
              ('Cause and Effect 1', 'Cause and Effect', [30]), ('Cause and Effect 2', 'Cause and Effect', [32]),
              ('Cause and Effect 3', 'Cause and Effect', [34]), ('Cause and Effect 4', 'Cause and Effect', [36]),
              ('Going to the Movies', 'Making Predictions/Conclusions', [38]), ('Reward', 'Making Predictions/Conclusions', [40]),
              ('In the Barn', 'Making Predictions/Conclusions', [42]), ('Rod and Wena', 'Making Predictions/Conclusions', [44])]
# the lesson guide printed before each Grade 2 activity sheet (teacher-only)
G2_GUIDES = {6: 5, 8: 7, 10: 9, 12: 11, 14: 13, 16: 15, 18: 17, 20: 19, 22: 21, 24: 23, 26: 25, 28: 27, 30: 29, 32: 31,
             34: 33, 36: 35, 38: 37, 40: 39, 42: 41, 44: 43}

G3_DAYS = [('A Rainy Day', 'Noting Details', [[15, 16]]), ('A Gift from Uncle', 'Noting Details', [[17, 18]]),
           ('Taking Care of Animals', 'Noting Details', [[19, 20]]), ('Lost and Found', 'Noting Details', [[21, 22]]),
           ('The Banana Peelings', 'Noting Details', [[23, 24]]),
           ('Getting the General Significance', 'Getting the General Significance', [[25], [26]]),
           ('Getting the General Significance', 'Getting the General Significance', [[27], [28]]),
           ('Getting the General Significance', 'Getting the General Significance', [[29], [30]]),
           ('Getting the General Significance', 'Getting the General Significance', [[31], [32]]),
           ('Getting the General Significance', 'Getting the General Significance', [[33], [34]]),
           ('Hearing Mass · The Big, Pink Bundle of Joy', 'Drawing Conclusions', [[35], [36]]),
           ('A Bookworm · The Ferris Wheel Ride', 'Drawing Conclusions', [[37], [38]]),
           ('Anti-Decay Vaccine · Scared Stiff', 'Drawing Conclusions', [[39], [40]]),
           ('Caught in the Rain · A Visit to the City', 'Drawing Conclusions', [[41], [42]]),
           ('First Elevator Ride · Falling in Line', 'Drawing Conclusions', [[43], [44]]),
           ('An Accident · A Surprise Gift', 'Predicting Outcomes', [[45], [46]]),
           ('Rico · Why Lina Cried', 'Predicting Outcomes', [[47], [48]]),
           ('A Letter from Father · A Boy’s Wish', 'Predicting Outcomes', [[49], [50]]),
           ('Mina’s Father · Getting Ready', 'Predicting Outcomes', [[51], [52]]),
           ('One Sunday Morning · Just Pray', 'Predicting Outcomes', [[53], [54]])]

G4_ACTS = [('Pepe and the Guavas', 'Noting Details', [16, 17]), ('At the Public Playground', 'Noting Details', [18, 19]),
           ('A Family of Music Lovers', 'Noting Details', [20, 21]), ('The Artesian Well', 'Noting Details', [22, 23]),
           ('Raindrops', 'Getting the General Significance', [24]), ('Carlos', 'Getting the General Significance', [25, 26]),
           ('Pal Is Sick', 'Getting the General Significance', [27, 28]), ('Flag', 'Getting the General Significance', [29]),
           ('A Joke', 'Predicting Outcomes and Making Inferences', [30]),
           ('The Birdlings', 'Predicting Outcomes and Making Inferences', [31]),
           ('The Pet Bird', 'Predicting Outcomes and Making Inferences', [32]),
           ('Banana Peelings', 'Predicting Outcomes and Making Inferences', [33]),
           ('Months of the Year', 'Following Precise Directions', [34]), ('The English Alphabet', 'Following Precise Directions', [35]),
           ('Our Nipa Hut', 'Following Precise Directions', [36]), ('Understanding Directions', 'Following Precise Directions', [37]),
           ('The Kingfisher and the Cat', 'Reading Exercises for Speed', [38]),
           ('A Dream That Became a Reality', 'Reading Exercises for Speed', [39]),
           ('The Ant and the Grasshopper', 'Reading Exercises for Speed', [40]),
           ('A Medal on the Barong Filipino', 'Reading Exercises for Speed', [41])]
G4_TEST = [('Ana’s Pet', 'Noting Details', [7, 8]), ('Exercise', 'Getting the General Significance', [9, 10]),
           ('Guava Jam', 'Predicting Outcomes', [11, 12]), ('Playing with Circles', 'Following Precise Directions', [13]),
           ('The Horse and the Snail', 'Reading Exercises for Speed', [14])]
G4_POST = [('Ana’s Pet', 'Noting Details', [43, 44]), ('Exercise', 'Getting the General Significance', [45, 46]),
           ('Guava Jam', 'Predicting Outcomes', [47, 48]), ('Playing with Circles', 'Following Precise Directions', [49]),
           ('The Horse and the Snail', 'Reading Exercises for Speed', [50])]

G5_ACTS = [('The Eyebrows', 'Noting Details', [16, 17]), ('Calla Lilies', 'Noting Details', [18, 19]),
           ('Counting the Rain', 'Noting Details', [20, 21]), ('The Enchanted Deer', 'Noting Details', [22, 23]),
           ('Green Plant', 'Getting the General Significance', [24]), ('The Lost Sheep', 'Getting the General Significance', [25]),
           ('Insects (Locusts)', 'Getting the General Significance', [26]), ('Linda Saves', 'Getting the General Significance', [27]),
           ('Hearing Mass', 'Predicting Outcomes and Making Inferences', [28]),
           ('The Big, Pink Bundle of Joy', 'Predicting Outcomes and Making Inferences', [29]),
           ('A Bookworm', 'Predicting Outcomes and Making Inferences', [30]),
           ('The Ferris Wheel Ride', 'Predicting Outcomes and Making Inferences', [31]),
           ('A Drawing Game', 'Reading to Follow Precise Directions', [32]), ('One Market Day', 'Reading to Follow Precise Directions', [33]),
           ('Drawing Shapes', 'Reading to Follow Precise Directions', [34]), ('Golden Shower', 'Reading to Follow Precise Directions', [35]),
           ('Our Forefathers', 'Reading Exercises for Speed and Comprehension', [36, 37]),
           ('Early Manhood of Father Gomez', 'Reading Exercises for Speed and Comprehension', [38, 39]),
           ('Insects', 'Reading Exercises for Speed and Comprehension', [40, 41]),
           ('Man’s First Clothes', 'Reading Exercises for Speed and Comprehension', [42, 43])]
G5_PRE = [('Magnets', 'Noting Details', [8]), ('Exercise 1', 'Getting the General Significance', [9]),
          ('Exercise 2', 'Getting the General Significance', [10]), ('Anti-Decay Vaccine', 'Predicting Outcomes', [11]),
          ('When Rizal Was a Boy', 'Reading to Follow Precise Directions', [12]), ('Insects', 'Reading Exercises for Speed', [13, 14])]
G5_CHECK10 = [('Why We Cook the Food We Eat', 'Noting Details', [45, 46]), ('Exercise 7', 'Getting the General Significance', [47]),
              ('Why Horses Need Iron Shoes', 'Predicting Outcomes', [48]), ('Father Was Late', 'Reading to Follow Precise Directions', [49]),
              ('Ice Cream', 'Reading Exercises for Speed', [50, 51])]
G5_CHECK20 = [('The Legend of the Poinsettia', 'Noting Details', [52, 53]), ('Exercise 8', 'Getting the General Significance', [54]),
              ('A Mother’s Expectation', 'Predicting Outcomes', [55]), ('A Surprise for Rolando', 'Reading to Follow Precise Directions', [56]),
              ('The Octopus', 'Reading Exercises for Speed', [57])]

G6_ACTS = [('Rules to Follow', 'Noting Details', [18, 19]), ('Daphne', 'Noting Details', [20, 21]),
           ('How the Romans Cooked Their Food', 'Noting Details', [22, 23]), ('First Night in the City', 'Noting Details', [24, 25]),
           ('Ed’s Day', 'Getting the General Significance', [26, 27]), ('The Crayfish', 'Getting the General Significance', [28]),
           ('Great Mind', 'Getting the General Significance', [29]), ('The Bible', 'Getting the General Significance', [30]),
           ('The Fox and the Stork', 'Predicting Outcomes and Making Inferences', [31]),
           ('Pete’s Catch', 'Predicting Outcomes and Making Inferences', [32]),
           ('The Red Sweater', 'Predicting Outcomes and Making Inferences', [33]),
           ('The Graduation Gift', 'Predicting Outcomes and Making Inferences', [34, 35]),
           ('The Earthworm', 'Reading to Follow Precise Directions', [36]),
           ('Rizal and the Filipino Youth', 'Reading to Follow Precise Directions', [37]),
           ('Christmas Eve', 'Reading to Follow Precise Directions', [38]),
           ('Hints for Keeping Friends', 'Reading to Follow Precise Directions', [39, 40]),
           ('Bethlehem', 'Reading Exercises for Speed', [41, 42]), ('Birds of Paradise', 'Reading Exercises for Speed', [43, 44]),
           ('Birthstones', 'Reading Exercises for Speed', [45, 46]), ('Apples', 'Reading Exercises for Speed', [47, 48])]
G6_PRE = [('The Face in the Pool', 'Noting Details', [9, 10]), ('Jay’s Curiosity', 'Getting the General Significance', [11]),
          ('Rice', 'Predicting Outcomes and Making Inferences', [12]), ('Wally’s Kite', 'Reading to Follow Precise Directions', [13]),
          ('A Fairy Tale from Japan', 'Reading Exercises for Speed and Comprehension', [14, 15, 16])]
G6_POST = [('The Face in the Pool', 'Noting Details', [50, 51]), ('Jay’s Curiosity', 'Getting the General Significance', [52]),
           ('Rice', 'Predicting Outcomes and Making Inferences', [53]), ('Wally’s Kite', 'Reading to Follow Precise Directions', [54]),
           ('A Fairy Tale from Japan', 'Reading Exercises for Speed and Comprehension', [55, 56, 57])]

G3_TEST = [('Growing Vegetables', 'Noting Details', [7, 8]), ('Fowls', 'Getting the General Significance', [9]),
           ('The First Monkey', 'Drawing Conclusions', [10, 11]), ('The Bees', 'Predicting Outcomes', [12, 13])]
G3_POST = [('Growing Vegetables', 'Noting Details', [56, 57]), ('Fowls', 'Getting the General Significance', [58, 59]),
           ('The First Monkey', 'Drawing Conclusions', [60, 61]), ('The Bees', 'Predicting Outcomes', [62, 63])]


def _units(items):
    return [dict(title=t, skill=s, pages=p) for t, s, p in items]


def plan():
    P = {}
    P[1] = dict(teacher=[36, 37], lessons=[
        dict(kind='pre', title='Pre-test', units=[dict(title='Pre-test · Reading', skill='Noting Details', pages=[6, 7], part='a'),
                                                  dict(title='Pre-test · Reality or Fantasy', skill='Fantasy or Reality', pages=[7], part='b'),
                                                  dict(title='Pre-test · Sequencing', skill='Sequencing Events', pages=[7], part='c'),
                                                  dict(title='Pre-test · Cause and Effect', skill='Cause and Effect', pages=[8])])]
        + [dict(kind='lesson', title=t, skill=s, units=[dict(title=t, skill=s, pages=p)]) for t, s, p in G1_LESSONS]
        + [dict(kind='post', title='Post-test', units=[dict(title='Post-test · Reading', skill='Noting Details', pages=[33], part='a'),
                                                       dict(title='Post-test · Reality or Fantasy', skill='Fantasy or Reality', pages=[33], part='b'),
                                                       dict(title='Post-test · Sequencing', skill='Sequencing Events', pages=[34], part='a'),
                                                       dict(title='Post-test · Cause and Effect', skill='Cause and Effect', pages=[34], part='b')])])
    g2_test = [dict(title='Pretest · Reading', skill='Noting Details', pages=[45], part='a'),
               dict(title='Pretest · Reality or Fantasy', skill='Fantasy and Reality', pages=[45], part='b'),
               dict(title='Pretest · Sequencing', skill='Sequencing Events', pages=[45], part='c'),
               dict(title='Pretest · Cause and Effect', skill='Cause and Effect', pages=[46], part='a'),
               dict(title='Pretest · In the Barn', skill='Making Predictions/Conclusions', pages=[46, 47], part='b')]
    P[2] = dict(teacher=sorted(G2_GUIDES.values()) + [49, 50], guides=G2_GUIDES, lessons=[
        dict(kind='pre', title='Pretest', units=g2_test)]
        + [dict(kind='lesson', title=t, skill=s, units=[dict(title=t, skill=s, pages=p)]) for t, s, p in G2_LESSONS]
        + [dict(kind='post', title='Post Test', units=[dict(u, title=u['title'].replace('Pretest', 'Post Test')) for u in g2_test])])
    P[3] = dict(teacher=[64, 65, 66], lessons=[dict(kind='pre', title='Pre-test', units=_units(G3_TEST))]
                + [dict(kind='lesson', title=t, skill=s, units=[dict(title=t.split(' · ')[i] if ' · ' in t else t, skill=s, pages=p)
                                                                 for i, p in enumerate(ps)]) for t, s, ps in G3_DAYS]
                + [dict(kind='post', title='Post-test', units=_units(G3_POST))])
    P[4] = dict(teacher=[51, 52, 53], lessons=[dict(kind='pre', title='Pre-Assessment', units=_units(G4_TEST))]
                + [dict(kind='lesson', title=t, skill=s, units=[dict(title=t, skill=s, pages=p)]) for t, s, p in G4_ACTS]
                + [dict(kind='post', title='Post-Assessment', units=_units(G4_POST))])
    P[5] = dict(teacher=[58, 59, 60, 61, 62], lessons=[dict(kind='pre', title='Pre-Assessment', units=_units(G5_PRE))]
                + [dict(kind='lesson', title=t, skill=s, units=[dict(title=t, skill=s, pages=p)]) for t, s, p in G5_ACTS[:10]]
                + [dict(kind='check', title='Formative Assessment after Activity 10', units=_units(G5_CHECK10))]
                + [dict(kind='lesson', title=t, skill=s, units=[dict(title=t, skill=s, pages=p)]) for t, s, p in G5_ACTS[10:]]
                + [dict(kind='post', title='Formative Assessment after Activity 20', units=_units(G5_CHECK20))])
    P[6] = dict(teacher=[58, 59, 60], lessons=[dict(kind='pre', title='Pre-Assessment', units=_units(G6_PRE))]
                + [dict(kind='lesson', title=t, skill=s, units=[dict(title=t, skill=s, pages=p)]) for t, s, p in G6_ACTS]
                + [dict(kind='post', title='Post-Assessment', units=_units(G6_POST))])
    return P
